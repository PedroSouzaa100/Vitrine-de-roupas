"""Integra??o real HTTP/MySQL em banco tempor?rio. N?o altera dados do site."""
from pathlib import Path
import tempfile
import os, subprocess, json, secrets, urllib.request, urllib.error, urllib.parse, http.cookiejar, re, time, base64, socket, sys
ROOT=Path(__file__).resolve().parent.parent
PHP='C:/xampp/php/php.exe'
ENV=os.environ.copy()
ENV.update(APP_ENV='development',APP_URL='',APP_BASE_PATH='',DB_HOST='localhost',DB_NAME='vitrine_test_catalog_'+secrets.token_hex(6),DB_USER='root',DB_PASS='',STORE_DOMAIN='',STORE_SUBDOMAINS='')
PNG=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=')
def fixture(mode,data=None):
 p=subprocess.run([PHP,'tools/catalog-fixture.php',mode],cwd=ROOT,env=ENV,input=json.dumps(data) if data else None,text=True,capture_output=True)
 if p.returncode: raise AssertionError(p.stderr+p.stdout)
 return json.loads(p.stdout) if mode!='cleanup' else p.stdout

def query(sql,params=[]): return fixture('query',dict(sql=sql,params=params))
class Browser:
 def __init__(self):
  self.cookies=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))
 def call(self,path,data=None,files=None,headers=None):
  headers=headers or {}
  if files is not None:
   boundary='VitrineTest'+secrets.token_hex(8);parts=[]
   for key,value in (data or {}).items():
    for v in value if isinstance(value,list) else [value]: parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{v}\r\n').encode())
   for key,name,content,mime in files:
    parts.append((f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n').encode()+content+b'\r\n')
   body=b''.join(parts)+(f'--{boundary}--\r\n').encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
  else: body=urllib.parse.urlencode(data,doseq=True).encode() if data is not None else None
  req=urllib.request.Request(BASE+path,data=body,headers=headers)
  try:
   r=self.opener.open(req,timeout=15);status=r.status;url=r.url;text=r.read().decode()
  except urllib.error.HTTPError as e: status=e.code;url=e.url;text=e.read().decode()
  assert 'Fatal error' not in text and 'Warning:' not in text and '<b>Warning</b>' not in text,(path,text[:600])
  return status,text,url
 def get(self,path): return self.call(path)
 def api(self,action=None,**data):
  if action is None:
   status,text,_=self.get('cart-api.php');self.token=json.loads(text)['csrf_token'];return status,json.loads(text)
  data.update(action=action,csrf_token=self.token)
  status,text,_=self.call('cart-api.php',data);result=json.loads(text)
  if 'csrf_token' in result:self.token=result['csrf_token']
  return status,result

def field(html,name):
 m=re.search(r'name="'+re.escape(name)+r'" value="([^"]*)"',html);assert m,name;return m.group(1)

def ok(label): print('OK',label,flush=True)
server=None;setup=False
session_dir=tempfile.TemporaryDirectory(prefix="vitrine-catalog-sessions-")
try:
 info=fixture('setup');setup=True
 sock=socket.socket();sock.bind(('127.0.0.1',0));port=sock.getsockname()[1];sock.close()
 BASE=f'http://127.0.0.1:{port}/'
 server=subprocess.Popen([PHP,'-d','sys_temp_dir='+session_dir.name,'-d','session.save_path='+session_dir.name,'-d','post_max_size=64M','-d','upload_max_filesize=5M','-S',f'127.0.0.1:{port}','-t',str(ROOT)],cwd=ROOT,env=ENV,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 for _ in range(50):
  try: urllib.request.urlopen(BASE+'login.php',timeout=1);break
  except Exception: time.sleep(.1)
 a,b,guest=Browser(),Browser(),Browser()
 for browser,key in [(a,'alpha'),(b,'beta')]:
  status,html,_=browser.get('login.php');token=field(html,'csrf_token')
  status,html,url=browser.call('login.php',dict(csrf_token=token,email=key+'@catalog.test',password=info['password']))
  assert 'seller/dashboard.php' in url,(status,url,html[:500])
 ok('autenticacao e dashboard dos dois vendedores')
 status,html,_=a.get('seller/store.php');storeToken=field(html,'csrf_token')
 storeData=dict(csrf_token=storeToken,name='Loja alpha',whatsapp='123',category_id='1')
 status,text,_=a.call('seller/store.php',storeData);assert 'WhatsApp com' in text
 storeData['whatsapp']='+55 (11) 99999-9999'
 status,html,url=a.call('seller/store.php',storeData);assert 'seller/store.php' in url and 'Loja atualizada' in html
 assert query('SELECT whatsapp FROM stores WHERE id=?',[info['stores']['alpha']])[0]['whatsapp']=='5511999999999'
 ok('WhatsApp do vendedor: formato invalido rejeitado e numero internacional salvo na loja correta')
 def form(browser,id=0):
  return browser.get('seller/product-form.php'+('?id='+str(id) if id else ''))[1]
 def data(html,name='Produto de teste',**extra):
  result=dict(csrf_token=field(html,'csrf_token'),product_revision=field(html,'product_revision'),name=name,description='Descricao do produto',price='12.30',promo_price='10.05',stock='20',category_id='4',status='active')
  result['photo_order[]']=re.findall(r'name="photo_order\[\]" value="([^"]+)"',html)
  result.update(extra);return result
 html=form(a);status,text,_=a.call('seller/product-form.php',data(html),[]);assert '1 a 5 fotos' in text
 bad=data(html);bad['price']='1e3';status,text,_=a.call('seller/product-form.php',bad,[]);assert 'duas casas decimais' in text
 ok('foto obrigatoria e preco numerico estrito')
 html=form(a);status,_,url=a.call('seller/product-form.php',data(html),[('photos[slot0]','foto.png',PNG,'image/png')]);assert status==200 and 'products.php' in url,url
 id=query('SELECT id FROM products WHERE store_id=?',[info['stores']['alpha']])[0]['id']
 ok('cadastro com uma imagem real e campos opcionais ausentes')
 html=form(a,id);status,_,url=a.call('seller/product-form.php?id='+str(id),data(html),[(f'photos[slot{i}]',f'foto{i}.png',PNG,'image/png') for i in range(1,5)]);assert 'products.php' in url
 assert len(query('SELECT id FROM product_images WHERE product_id=?',[id]))==4
 old=query('SELECT main_image FROM products WHERE id=?',[id])[0]['main_image']
 ok('edicao para exatamente cinco fotos')
 oldTab=form(a,id)
 query('UPDATE products SET name=? WHERE id=?',['Alterado em outra aba',id])
 status,text,_=a.call('seller/product-form.php?id='+str(id),data(oldTab),[]);assert 'outra p' in text
 ok('edicao concorrente nao sobrescreve alteracoes de outra aba')
 html=form(a,id);status,text,_=a.call('seller/product-form.php?id='+str(id),data(html),[(f'photos[slot{i}]',f'foto{i}.png',PNG,'image/png') for i in range(6)]);assert 'cinco fotos' in text or '1 a 5' in text
 assert len(query('SELECT id FROM product_images WHERE product_id=?',[id]))==4
 ok('backend rejeita seis fotos sem alterar o produto')
 html=form(a,id);payload=data(html);payload['photo_order[]']=['slot4','slot0','slot1','slot2','slot3'];payload['remove_photos[]']=['slot1']
 status,_,url=a.call('seller/product-form.php?id='+str(id),payload,[('photos[slot2]','substituta.png',PNG,'image/png')]);assert 'products.php' in url
 row=query('SELECT main_image FROM products WHERE id=?',[id])[0];assert row['main_image']!=old
 assert len(query('SELECT id FROM product_images WHERE product_id=?',[id]))==3
 ok('remocao, substituicao, reordenacao e nova imagem principal')
 pathsBefore=set((ROOT/'uploads/products').glob('*'))
 html=form(a,id);status,text,_=a.call('seller/product-form.php?id='+str(id),data(html),[('photos[slot0]','valida.png',PNG,'image/png'),('photos[slot1]','maliciosa.php',b'<?php echo 1;','image/png')]);assert 'imagem' in text
 assert set((ROOT/'uploads/products').glob('*'))==pathsBefore
 ok('arquivo falso rejeitado e novos uploads revertidos sem apagar fotos salvas')
 html=form(a,id);bad=data(html);bad['category_id']='999999';status,text,_=a.call('seller/product-form.php?id='+str(id),bad,[]);assert 'categoria v' in text
 assert b.get('seller/product-form.php?id='+str(id))[0]==404
 assert b.call('seller/product-form.php?id='+str(id),data(form(b)),[])[0]==404
 assert b.get('seller/product-delete.php?id='+str(id))[0]==405
 assert b.call('seller/product-delete.php',dict(id=id))[0]==419
 assert b.call('seller/product-delete.php',dict(id=id,csrf_token=field(form(b),'csrf_token')))[0]==404
 ok('categoria invalida, isolamento entre vendedores e exclusao POST com CSRF')
 html=form(b);status,_,url=b.call('seller/product-form.php',data(html,name='Produto beta'),[('photos[slot0]','b.png',PNG,'image/png')]);assert 'products.php' in url
 other=query('SELECT id FROM products WHERE store_id=?',[info['stores']['beta']])[0]['id']
 slug=query('SELECT slug FROM products WHERE id=?',[id])[0]['slug']
 assert guest.get('store.php?slug=alpha')[0]==200
 productHtml=guest.get('product.php?slug='+slug)[1];assert 'data-cart-add' in productHtml
 assert guest.get('cart.php')[0]==200
 status,state=guest.api();assert status==200 and state['cart']['count']==0
 assert guest.call('cart-api.php',dict(action='add',product_id=id,quantity=1))[0]==419
 status,state=guest.api('add',product_id=id,quantity=2,price='0.01');assert status==200 and state['cart']['total_cents']==2010
 status,state=guest.api('add',product_id=id,quantity=1);assert state['cart']['count']==3 and len(state['cart']['items'])==1
 status,state=guest.api('add',product_id=other,quantity=1);assert status==409 and state['cart']['count']==3
 status,state=guest.api('update',product_id=id,quantity=4);assert state['cart']['total_cents']==4020
 assert guest.api('update',product_id=id,quantity=-1)[0]==422
 assert guest.api('update',product_id=id,quantity='1.5')[0]==422
 assert guest.api('update',product_id=id,quantity=21)[0]==409
 ok('carrinho: totais em centavos, preco do servidor, soma sem duplicar, quantidades e bloqueio entre lojas')
 status,state=guest.api();revision=state['cart']['revision']
 status,checkout=guest.api('checkout',revision=revision);assert status==200
 wa=urllib.parse.urlparse(checkout['url']);message=urllib.parse.parse_qs(wa.query)['text'][0]
 assert wa.netloc=='wa.me' and wa.path=='/5511999999999' and 'R$ 40,20' in message and '4x ' in message
 assert guest.api()[1]['cart']['count']==4
 query('UPDATE products SET promo_price=? WHERE id=?',['9.99',id])
 status,state=guest.api('checkout',revision=revision);assert status==409 and state['cart']['total_cents']==3996
 status,state=guest.api('checkout',revision=state['cart']['revision']);assert status==200
 query('UPDATE stores SET whatsapp=? WHERE id=?',['123',info['stores']['alpha']])
 status,state=guest.api('checkout',revision=state['cart']['revision']);assert status==409
 query('UPDATE stores SET whatsapp=? WHERE id=?',['5521988887777',info['stores']['alpha']])
 status,state=guest.api();assert state['cart']['can_checkout']
 status,state=guest.api('checkout',revision=state['cart']['revision']);assert state['url'].startswith('https://wa.me/5521988887777?')
 ok('WhatsApp correto, mensagem codificada, revisao de precos e telefone atualizado/invalidado')
 query('UPDATE products SET sizes=? WHERE id=?',['P,M,G,GG',id])
 status,state=guest.api();assert state['cart']['needs_size'] and not state['cart']['can_checkout']
 assert state['cart']['count']==4
 assert guest.api('checkout',revision=state['cart']['revision'])[0]==409
 line=state['cart']['items'][0]['key']
 status,state=guest.api('update',line_id=line,product_id=id,size='M',quantity=2);assert status==200 and state['cart']['items'][0]['size']=='M'
 status,state=guest.api('add',product_id=id,size='M',quantity=1);assert status==200 and state['cart']['count']==3 and len(state['cart']['items'])==1
 status,state=guest.api('add',product_id=id,size='G',quantity=2);assert status==200 and state['cart']['count']==5 and len(state['cart']['items'])==2
 m=next(item for item in state['cart']['items'] if item['size']=='M');g=next(item for item in state['cart']['items'] if item['size']=='G')
 assert m['key']!=g['key'] and m['max_quantity']==18 and g['max_quantity']==17
 assert guest.api('add',product_id=id,size='XL',quantity=1)[0]==422
 assert guest.api('add',product_id=id,quantity=1)[0]==422
 assert guest.api('add',product_id=id,quantity=1,**{'size[]':['M']})[0]==422
 assert guest.api('add',product_id=id,size='M',quantity=16)[0]==409
 assert guest.api('update',product_id=id,size='M',quantity=19)[0]==409
 assert guest.api('update',line_id=m['key'],product_id=id,size='M',quantity=19)[0]==409
 status,state=guest.api('checkout',revision=state['cart']['revision']);assert status==200
 text=urllib.parse.parse_qs(urllib.parse.urlparse(state['url']).query)['text'][0]
 assert 'Tam. M' in text and 'Tam. G' in text and '49,95' in text and 'Total (5 itens)' in text
 assert len(text.splitlines())==7
 status,state=guest.api('update',line_id=m['key'],product_id=id,size='G',quantity=1);assert status==200 and len(state['cart']['items'])==1 and state['cart']['count']==3
 assert state['cart']['items'][0]['size']=='G'
 status,state=guest.api('add',product_id=id,size='M',quantity=1);assert status==200
 g=next(item for item in state['cart']['items'] if item['size']=='G');m=next(item for item in state['cart']['items'] if item['size']=='M')
 status,state=guest.api('remove',line_id=g['key'],product_id=id);assert status==200 and state['cart']['count']==1 and state['cart']['items'][0]['size']=='M'
 query('UPDATE products SET sizes=? WHERE id=?',['G,GG',id]);status,state=guest.api();assert state['cart']['needs_size'] and state['cart']['items'][0]['size']=='M' and not state['cart']['can_checkout']
 assert guest.api('checkout',revision=state['cart']['revision'])[0]==409
 status,state=guest.api('update',line_id=m['key'],size='GG',quantity=1);assert status==200 and state['cart']['can_checkout']
 # Sessao antiga: preserva o item, mas exige escolher tamanho entre varias opcoes.
 code="require 'includes/cart.php'; $_SESSION['shopping_cart']=['store_id'=>"+str(info['stores']['alpha'])+",'items'=>["+str(id)+"=>2],'prices'=>["+str(id)+"=>999]]; echo json_encode(cart_snapshot(db()));"
 migrated=subprocess.run([PHP,'-r',code],cwd=ROOT,env=ENV,text=True,capture_output=True)
 legacy=json.loads(migrated.stdout);assert legacy['needs_size'] and legacy['count']==2 and not legacy['can_checkout']
 guest.api('clear');query('UPDATE products SET sizes=? WHERE id=?',['Unico',id]);status,state=guest.api('add',product_id=id,quantity=1);assert status==200 and state['cart']['items'][0]['size']=='Unico'
 guest.api('clear');query('UPDATE products SET sizes=? WHERE id=?',['',id]);guest.api('add',product_id=id,quantity=4)
 ok('tamanhos: selecao obrigatoria, M/G separados, soma por variante, estoque compartilhado, troca/mescla/remocao, sessao antiga e WhatsApp objetivo')

 query('UPDATE products SET stock=2 WHERE id=?',[id]);state=guest.api()[1];assert state['cart']['count']==2 and state['cart']['notices']
 query("UPDATE products SET status='inactive' WHERE id=?",[id]);state=guest.api()[1];assert state['cart']['count']==0
 assert guest.api('add',product_id=id)[0]==409
 query("UPDATE products SET status='active',stock=20 WHERE id=?",[id]);guest.api('add',product_id=id)
 query("UPDATE users SET status='blocked' WHERE id=?",[info['sellers']['alpha']]);assert guest.api()[1]['cart']['count']==0
 query("UPDATE users SET status='active' WHERE id=?",[info['sellers']['alpha']])
 guest.api('add',product_id=other);assert guest.api('remove',product_id=other)[1]['cart']['count']==0
 guest.api('add',product_id=id);assert guest.api('clear')[1]['cart']['count']==0
 assert guest.api('checkout',revision='')[0]==409
 assert guest.call('cart-api.php',headers={'Origin':'https://attacker.example'})[0]==403
 ok('estoque reduzido, indisponibilidade, vendedor bloqueado, remover, limpar, vazio e origem externa')
 guest.api('add',product_id=id)
 photos=query('SELECT main_image AS path FROM products WHERE id=? UNION SELECT image_path AS path FROM product_images WHERE product_id=?',[id,id])
 token=field(form(a,id),'csrf_token');status,_,url=a.call('seller/product-delete.php',dict(id=id,csrf_token=token));assert 'products.php' in url
 assert not query('SELECT id FROM products WHERE id=?',[id])
 assert all(not (ROOT/'uploads'/p['path']).exists() for p in photos)
 assert guest.api()[1]['cart']['count']==0
 ok('exclusao real, limpeza de imagens e reconciliacao do carrinho')
 if os.environ.get('BROWSER_TEST') == '1':
  import importlib.util
  spec=importlib.util.spec_from_file_location('browser_catalog',ROOT/'tools/browser-catalog.py')
  module=importlib.util.module_from_spec(spec);spec.loader.exec_module(module)
  module.run_browser(BASE,info,ROOT,PNG)
 admin=Browser();status,html,_=admin.get('admin/login.php')
 status,html,url=admin.call('admin/login.php',dict(csrf_token=field(html,'csrf_token'),email='admin@catalog.test',password=info['password']))
 assert 'admin/dashboard.php' in url
 sellerId=query('SELECT id FROM sellers WHERE user_id=?',[info['sellers']['beta']])[0]['id']
 path='admin/seller-view.php?id='+str(sellerId)
 status,html,_=admin.get(path);assert status==200 and 'Excluir vendedor e loja' in html
 before=query('SELECT id FROM stores WHERE id=?',[info['stores']['beta']])
 b.call(path,dict(action='delete',csrf_token=field(form(b),'csrf_token')))
 assert query('SELECT id FROM stores WHERE id=?',[info['stores']['beta']])==before
 assert admin.call(path,dict(action='delete'))[0]==419
 token=field(html,'csrf_token')
 status,html,url=admin.call(path,dict(action='suspend',csrf_token=token));assert status==200 and 'seller-view.php' in url
 assert guest.get('store.php?slug=beta')[0]==404
 status,html,url=admin.call(path,dict(action='reactivate',csrf_token=token));assert status==200
 assert guest.get('store.php?slug=beta')[0]==200
 betaPhotos=query('SELECT main_image AS path FROM products WHERE id=?',[other])
 query('INSERT INTO favorites (user_id,product_id) VALUES (?,?)',[info['sellers']['alpha'],other])
 query('INSERT INTO store_favorites (user_id,store_id) VALUES (?,?)',[info['sellers']['alpha'],info['stores']['beta']])
 status,html,url=admin.call(path,dict(action='delete',csrf_token=token));assert 'admin/sellers.php' in url
 assert not query('SELECT id FROM users WHERE id=?',[info['sellers']['beta']])
 assert not query('SELECT id FROM sellers WHERE id=?',[sellerId])
 assert not query('SELECT id FROM stores WHERE id=?',[info['stores']['beta']])
 assert not query('SELECT id FROM products WHERE id=?',[other])
 assert not query('SELECT id FROM favorites WHERE product_id=?',[other])
 assert all(not (ROOT/'uploads'/photo['path']).exists() for photo in betaPhotos)
 assert query("SELECT id FROM admin_logs WHERE action='delete_seller' AND target_id=?",[sellerId])
 ok('admin: autorizacao, CSRF, suspender/reativar, exclusao em cascata, fotos e registro de auditoria')
 print('TODOS OS TESTES HTTP/MYSQL PASSARAM.',flush=True)
finally:
 if server:server.terminate();server.wait(timeout=10)
 if setup:print(fixture('cleanup'),flush=True)
 session_dir.cleanup()
