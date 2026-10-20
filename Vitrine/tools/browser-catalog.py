"""Teste de interface com Chrome headless e CDP; somente bibliotecas padrão do Python."""
import base64
import json
import os
from pathlib import Path
import socket
import struct
import subprocess
import tempfile
import time
import urllib.request
import urllib.parse


class DevTools:
    def __init__(self, url):
        parsed = urllib.parse.urlparse(url)
        self.socket = socket.create_connection((parsed.hostname, parsed.port), timeout=40)
        key = base64.b64encode(os.urandom(16)).decode()
        request = f'GET {parsed.path} HTTP/1.1\r\nHost: {parsed.netloc}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\n\r\n'
        self.socket.sendall(request.encode())
        header = b''
        while not header.endswith(b'\r\n\r\n'): header += self.socket.recv(1)
        assert header.startswith(b'HTTP/1.1 101 '), header
        self.sequence = 0
        self.exceptions = []

    def read(self, size):
        data = b''
        while len(data) < size:
            chunk = self.socket.recv(size - len(data))
            if not chunk: raise RuntimeError('Chrome encerrou a conexão.')
            data += chunk
        return data

    def send(self, data, opcode=1):
        mask = os.urandom(4)
        length = len(data)
        header = bytes([0x80 | opcode])
        header += bytes([0x80 | length]) if length < 126 else bytes([0x80 | 126]) + struct.pack('!H', length) if length < 65536 else bytes([0x80 | 127]) + struct.pack('!Q', length)
        self.socket.sendall(header + mask + bytes(value ^ mask[index % 4] for index, value in enumerate(data)))

    def call(self, method, **params):
        self.sequence += 1
        current = self.sequence
        self.send(json.dumps(dict(id=current, method=method, params=params)).encode())
        while True:
            first, second = self.read(2)
            length = second & 127
            if length == 126: length = struct.unpack('!H', self.read(2))[0]
            elif length == 127: length = struct.unpack('!Q', self.read(8))[0]
            mask = self.read(4) if second & 128 else None
            data = self.read(length)
            if mask: data = bytes(value ^ mask[index % 4] for index, value in enumerate(data))
            if first & 15 == 9: self.send(data, 10); continue
            if first & 15 == 8: raise RuntimeError('Chrome fechou o websocket.')
            if first & 15 != 1: continue
            message = json.loads(data)
            if message.get('method') == 'Runtime.exceptionThrown': self.exceptions.append(message['params'])
            if message.get('id') == current:
                if 'error' in message: raise RuntimeError(message['error'])
                return message.get('result', {})

    def js(self, expression):
        result = self.call('Runtime.evaluate', expression=expression, awaitPromise=True, returnByValue=True)
        if 'exceptionDetails' in result: raise AssertionError(result['exceptionDetails'])
        return result.get('result', {}).get('value')

    def wait(self, expression, seconds=20):
        deadline = time.monotonic() + seconds
        while time.monotonic() < deadline:
            if self.js(expression): return
            time.sleep(.1)
        raise AssertionError('Tempo excedido: ' + expression + ' | ' + str(self.js('({url:location.href,text:document.body.innerText.slice(0,700)})')))

    def navigate(self, url):
        self.call('Page.navigate', url=url)
        self.wait('location.href === ' + json.dumps(url) + ' && document.readyState === "complete"', 30)

    def screenshot(self, path, width, height):
        self.call('Emulation.setDeviceMetricsOverride', width=width, height=height, deviceScaleFactor=1, mobile=width < 600)
        self.js('window.scrollTo(0,0)')
        time.sleep(.4)
        assert self.js('document.documentElement.scrollWidth <= innerWidth + 1'), 'Overflow horizontal'
        data = self.call('Page.captureScreenshot', format='png')
        path.write_bytes(base64.b64decode(data['data']))


def run_browser(base, info, root, png):
    chrome = Path('C:/Program Files/Google/Chrome/Application/chrome.exe')
    if not chrome.exists(): raise RuntimeError('Chrome não encontrado para o teste visual.')
    with tempfile.TemporaryDirectory(prefix='vitrine-browser-', ignore_cleanup_errors=True) as folder:
        folder = Path(folder)
        profile = folder / 'profile'
        process = subprocess.Popen([str(chrome), '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check', '--disable-extensions', '--remote-debugging-port=0', '--user-data-dir=' + str(profile), 'about:blank'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW)
        try:
            marker = profile / 'DevToolsActivePort'
            deadline = time.monotonic() + 25
            while not marker.exists() and time.monotonic() < deadline: time.sleep(.1)
            assert marker.exists(), 'Chrome não iniciou'
            port = marker.read_text().splitlines()[0]
            pages = json.loads(urllib.request.urlopen('http://127.0.0.1:' + port + '/json/list').read())
            page = next(page for page in pages if page['type'] == 'page')
            cdp = DevTools(page['webSocketDebuggerUrl'])
            cdp.call('Page.enable'); cdp.call('Runtime.enable')
            cdp.navigate(base + 'login.php')
            cdp.js('document.querySelector("[name=email]").value="alpha@catalog.test";document.querySelector("[name=password]").value=' + json.dumps(info['password']) + ';document.querySelector("[name=password]").closest("form").requestSubmit()')
            cdp.wait('location.pathname.endsWith("seller/dashboard.php") && document.readyState === "complete"')
            cdp.navigate(base + 'seller/store.php')
            assert cdp.js('document.querySelector("[name=whatsapp]").checkValidity()')
            cdp.navigate(base + 'seller/product-form.php')
            cdp.js('document.querySelector("[name=name]").value="Produto do navegador";document.querySelector("[name=price]").value="19.90";document.querySelector("[name=promo_price]").value="14.95";document.querySelector("[name=stock]").value="10";document.querySelector("[name=sizes]").value="M,G";document.querySelector("[name=category_id]").value="4"')
            photo = folder / 'photo.png'; photo.write_bytes(png)
            document = cdp.call('DOM.getDocument')['root']['nodeId']
            inputs = cdp.call('DOM.querySelectorAll', nodeId=document, selector='[data-photo-file]')['nodeIds']
            assert len(inputs) == 5
            for node in inputs: cdp.call('DOM.setFileInputFiles', nodeId=node, files=[str(photo)])
            assert cdp.js('document.querySelector("[data-photo-count]").textContent.startsWith("5 de 5")')
            assert cdp.js('[...document.querySelectorAll("[data-photo-preview]")].every(image => image.src.startsWith("blob:"))')
            cdp.js('document.querySelectorAll("[data-photo-first]")[4].click()')
            assert cdp.js('document.querySelector("[name=\\"photo_order[]\\"]").value === "slot4"')
            assert cdp.js('document.querySelector("[data-product-form]").checkValidity()')
            cdp.js('document.querySelector("[data-product-form]").requestSubmit()')
            cdp.wait('location.pathname.endsWith("seller/products.php") && document.readyState === "complete"')
            print('OK navegador: formulário, cinco prévias, imagem principal e cadastro real', flush=True)
            cdp.navigate(base + 'store.php?slug=alpha')
            cdp.wait('document.querySelector("[data-cart-add]:not(:disabled)") !== null')
            cdp.js('document.querySelector("[data-cart-add]:not(:disabled)").click()')
            assert cdp.js('document.querySelector("[data-cart-count]").textContent === "0"')
            cdp.js('document.querySelector("[data-cart-size]").value="M";document.querySelector("[data-cart-size]").dispatchEvent(new Event("change",{bubbles:true}));document.querySelector("[data-cart-add]").click()')
            cdp.wait('document.querySelector("[data-cart-count]").textContent === "1"')
            product_url=cdp.js('document.querySelector(".product-title a").href')
            cdp.navigate(product_url)
            cdp.js('document.querySelector("[data-cart-size]").value="G";const a=document.querySelector("[data-wa-inquiry]");a.addEventListener("click",event=>event.preventDefault(),{once:true});a.click()')
            assert 'tamanho G' in cdp.js('new URL(document.querySelector("[data-wa-inquiry]").href).searchParams.get("text")')
            cdp.navigate(base + 'cart.php')
            cdp.wait('document.querySelector("[data-cart-count]").textContent === "1"')
            cdp.js('document.querySelector("[data-cart-step=\\"1\\"]").click()')
            cdp.wait('document.querySelector("[data-cart-count]").textContent === "2"')
            assert cdp.js('document.querySelector("[data-cart-total]").textContent.replace(/\\s/g, "") === "R$29,90"')
            cdp.js('window.oldLine=document.querySelector("[data-cart-item]").dataset.cartItem;const s=document.querySelector("[data-cart-line-size]");s.value="G";s.dispatchEvent(new Event("change",{bubbles:true}))')
            cdp.wait('document.querySelector("[data-cart-item]").dataset.cartItem !== window.oldLine')
            assert cdp.js('document.querySelector("[data-cart-line-size]").value === "G"')
            cdp.screenshot(root / 'dist/cart-mobile.png', 390, 844)
            cdp.navigate(base + 'store.php?slug=beta')
            cdp.wait('document.querySelector("[data-cart-add]:not(:disabled)") !== null')
            cdp.js('document.querySelector("[data-cart-add]").click()')
            cdp.wait('document.querySelector("[data-cart-message]").textContent.includes("outra loja")')
            assert cdp.js('document.querySelector("[data-cart-count]").textContent === "2"')
            cdp.navigate(base + 'store.php?slug=alpha')
            cdp.wait('document.querySelector("[data-cart-add]:not(:disabled)") !== null')
            cdp.screenshot(root / 'dist/catalog-desktop.png', 1280, 900)
            cdp.navigate(base + 'cart.php')
            cdp.wait('document.querySelector("[data-cart-count]").textContent === "2"')
            result = cdp.js('(async()=>{const s=await (await fetch("cart-api.php")).json(); return await (await fetch("cart-api.php", {method:"POST", body:new URLSearchParams({action:"checkout", csrf_token:s.csrf_token,revision:s.cart.revision})})).json();})()')
            assert result['url'].startswith('https://wa.me/5521988887777?')
            assert 'Tam. G' in urllib.parse.parse_qs(urllib.parse.urlparse(result['url']).query)['text'][0]
            assert not cdp.exceptions, cdp.exceptions
            print('OK navegador: adicionar, recarregar, alterar quantidade, isolamento entre lojas, WhatsApp e layouts desktop/mobile sem overflow', flush=True)
            try: cdp.call('Browser.close')
            except (OSError, RuntimeError): pass
            cdp.socket.close()
        finally:
            if process.poll() is None: process.terminate()
            process.wait(timeout=15)
            time.sleep(.5)
