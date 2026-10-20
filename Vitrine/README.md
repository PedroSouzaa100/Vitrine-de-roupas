# Vitrine

Plataforma PHP e MySQL para divulgar lojas e produtos, com painel administrativo, painel do vendedor e carrinho.

## Requisitos

- PHP 8.0 ou superior, com PDO MySQL.
- MySQL 5.7 ou 8.0.
- Apache com suporte a `.htaccess` e `mod_rewrite` (por exemplo, XAMPP).
- Python 3 apenas para as ferramentas opcionais em `tools/`.

## Instalação local

1. Coloque o projeto no diretório público do Apache.
2. Importe `database.sql` em uma instalação nova. O script remove e recria tabelas; não execute em um banco com dados que deseja preservar.
3. Copie `config/local.example.php` para `config/local.php` e preencha a configuração do ambiente. Para XAMPP, use `APP_ENV = development`, `APP_URL = http://localhost`, `APP_BASE_PATH = /Vitrine` e as credenciais do seu MySQL.
4. Se existir `config/development.php`, revise-o: ele sobrescreve a configuração local no XAMPP e na linha de comando. Variáveis de ambiente têm prioridade sobre os dois arquivos.
5. Garanta permissão de escrita em `logs/`, `uploads/products/` e `uploads/stores/`.
6. Acesse `http://localhost/Vitrine/`. Para configurar a hospedagem e o administrador, consulte [HOSTINGER.md](HOSTINGER.md).

## Estrutura

| Caminho | Conteúdo |
| --- | --- |
| `*.php` na raiz | Páginas públicas e endpoints |
| `admin/` | Administração |
| `seller/` | Painel do vendedor |
| `includes/` | Autenticação, funções e componentes compartilhados |
| `config/` | Configuração da aplicação e conexão com o banco |
| `assets/` | CSS, JavaScript e imagens da interface |
| `uploads/` | Imagens enviadas pelos usuários; fora do Git |
| `logs/` | Logs locais; fora do Git |
| `tools/` | Ferramentas de manutenção, validação e publicação |
| `dist/` | Pacotes e backups locais; fora do Git |

Os caminhos das páginas existentes são mantidos para preservar os links da aplicação.

## Documentação

- [Hospedagem na Hostinger](HOSTINGER.md)
- [Subdomínios](SUBDOMINIOS.md)

## Antes de enviar ao GitHub

Não publique `config/local.php`, `config/development.php`, arquivos `.env`, backups, logs ou dados enviados pelos usuários. O `.gitignore` cobre esses arquivos; arquivos já rastreados precisam ser removidos do índice do Git separadamente.

Use somente valores fictícios nos exemplos. Se uma credencial real já tiver sido compartilhada ou versionada, substitua-a no provedor; removê-la do arquivo não a remove do histórico.

Revise os arquivos preparados para o commit com `git diff --cached` antes de publicar.
