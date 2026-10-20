# Publicar a Vitrine na Hostinger

## 1. Preparar hospedagem

Use hospedagem PHP/MySQL. No hPanel selecione PHP 8.2 ou superior e habilite pdo_mysql, mbstring, fileinfo, iconv e session. Configure upload_max_filesize=5M, post_max_size=64M, max_file_uploads=12, display_errors=Off e log_errors=On. PHP 8.3/8.4 tambem atende aos recursos usados; a verificacao local foi feita com o PHP instalado no XAMPP.

Aponte o dominio para a hospedagem e ative SSL e HTTPS no hPanel antes de instalar. O aplicativo exige HTTPS em producao. Nao habilite proxies/CDN com conexao HTTP entre proxy e origem; use HTTPS ate a origem para evitar loops.

## 2. Banco: instalacao nova ou migracao

Crie o banco e o usuario no hPanel e anote host, nome completo, usuario e senha.

- Instalacao nova: selecione um banco VAZIO no phpMyAdmin e importe database.hostinger.sql. Este arquivo nao cria banco nem apaga tabelas.
- Migracao deste XAMPP: exporte o banco vitrine ATUAL pelo phpMyAdmin local e importe no banco criado na Hostinger. Preserve dados e imagens de uploads/. Nao importe database.sql nem database.hostinger.sql sobre o banco existente. O ZIP nao contem uma exportacao dos seus dados.

O database.sql original apaga tabelas: mantenha-o somente para desenvolvimento.

## 3. Arquivos e credenciais

Envie o conteudo de dist/vitrine-hostinger.zip para public_html e extraia ali (index.php deve ficar diretamente em public_html). Inclua .htaccess e .user.ini. O pacote inclui as imagens presentes ao gera-lo; copie imagens adicionadas depois. Em migracoes preserve a pasta uploads do servidor.

Copie config/local.example.php para config/local.php e preencha APP_URL (https://seu-dominio.com.br, sem caminho), DB_HOST, DB_NAME, DB_USER e DB_PASS. Use APP_ENV=production. APP_BASE_PATH deve ser vazio em public_html; use /vitrine se instalado nessa subpasta. As variaveis de ambiente, se presentes, tem prioridade.

Nao envie credenciais para Git. config/local.php e logs sao bloqueados por .htaccess. Sem credenciais de producao o sistema responde 503, sem usar o usuario root local. Diretorios normalmente usam 755 e arquivos 644; permita escrita pelo PHP em uploads/products, uploads/stores e logs. Nao use 777.

## 4. Primeiro administrador

Se migrou um banco com administrador, use seu login e mantenha INSTALL_TOKEN vazio.

Para instalacao nova, gere uma chave aleatoria de 64 caracteres (php -r "echo bin2hex(random_bytes(32));"), coloque em INSTALL_TOKEN no config/local.php e abra https://seu-dominio.com.br/install.php. Informe a chave no formulario e crie o administrador. A chave nao vai na URL. Depois esvazie INSTALL_TOKEN e remova install.php. O instalador remoto fica indisponivel com a chave vazia ou quando ja existe administrador.

## 5. Subdominios das lojas

No hPanel a Hostinger exige cadastrar cada subdominio; apenas adicionar DNS wildcard nao provisiona as lojas. Crie identificador-da-loja.seu-dominio.com.br, escolha a MESMA pasta public_html do sistema e ative SSL desse subdominio. Nao copie o aplicativo para uma pasta isolada: lojas precisam do mesmo codigo, banco e uploads.

Se seu plano nao permitir usar a mesma pasta, confirme a alternativa com o suporte antes de habilitar os links. Configure STORE_DOMAIN=seu-dominio.com.br e STORE_SUBDOMAINS=['identificador-da-loja', 'outra-loja'] em config/local.php somente depois de cada subdominio funcionar. Lojas fora dessa lista continuam com store.php?slug=... . Em VPS com virtual host wildcard e TLS configurados, STORE_SUBDOMAINS pode ser '*'. Nomes/identificadores precisam ser labels DNS validos (ate 63 caracteres). Cookies de login ficam restritos ao dominio principal.

## 6. Verificar publicacao

Confira pagina inicial, cadastro, login, aprovacao de vendedor, cadastro/edicao de produto com imagem, logo/banner, favoritos, troca de senha e retorno ao dashboard. Confira HTTP redirecionando para HTTPS. Acesse /config/local.php, /.user.ini, /logs/, /tools/ e /database.hostinger.sql: devem retornar 403. O instalador desativado deve retornar 404.

Se tiver SSH: php tools/check-hosting.php verifica PHP, extensoes, permissoes, limites, conexao e tabelas sem mostrar segredos. Configuracoes CLI podem diferir do PHP web. Erros completos ficam nos logs, nunca na tela em producao.

Antes de atualizar, salve backup do banco, uploads e config/local.php fora da pasta publica. Mantenha esses arquivos ao substituir o codigo. O arquivo SQL fornecido e para instalacao nova; nao e uma migracao de schema.

## Referencias oficiais

- https://www.hostinger.com/support/1884149-how-to-import-a-database-with-phpmyadmin-in-hostinger/
- https://support.hostinger.com/en/articles/1583405-how-to-create-and-delete-subdomains
- https://support.hostinger.com/en/articles/4468886-how-to-manage-a-records
- https://www.hostinger.com/support/php/

## Dominio desta instalacao: vitrine-moda.shop

No Gerenciador de Arquivos, confirme public_html/index.php. Copie public_html/config/local.example.php para public_html/config/local.php. O exemplo agora traz APP_URL=https://vitrine-moda.shop; preencha DB_NAME, DB_USER e DB_PASS com valores reais do seu hPanel e confira DB_HOST. Renomear o exemplo sem preencher o banco nao conclui a instalacao. O pacote nao inclui credenciais reais e nao cria/importa o banco automaticamente.

Abra https://vitrine-moda.shop diretamente: se aparecer "Configure o dominio HTTPS e o banco de producao", falta config/local.php ou algum valor obrigatorio; se aparecer "Nao foi possivel conectar ao banco", confira credenciais, host e importacao; se houver erro 500, confira PHP 8.2+ e logs no hPanel. Se houver uma pasta extra contendo index.php, mova seu conteudo para public_html.

O pacote e de um aplicativo PHP personalizado. Se o assistente de migracao nao o reconhecer, publique pelo Gerenciador de Arquivos e importe o banco pelo phpMyAdmin. Nao e um backup WordPress.
