<?php
// Copie para local.php e preencha. Nunca publique credenciais em repositorios.
return [
    'APP_ENV' => 'production',
    'APP_URL' => 'https://exemplo.com.br',
    'APP_BASE_PATH' => '', // Ex.: /vitrine, se instalado em subpasta.
    'DB_HOST' => 'localhost', // Use o host informado pelo hPanel.
    'DB_NAME' => 'PREENCHA_NOME_DO_BANCO',
    'DB_USER' => 'PREENCHA_USUARIO_DO_BANCO',
    'DB_PASS' => 'PREENCHA_SENHA_DO_BANCO',
    // Ative apenas depois de configurar os subdominios e certificados no hPanel.
    'STORE_DOMAIN' => '', // Ex.: seu-dominio.com.br
    'STORE_SUBDOMAINS' => [], // Slugs cadastrados no hPanel: ['minha-loja']; '*' apenas em VPS com wildcard.
    // Gere 64 caracteres com: php -r "echo bin2hex(random_bytes(32));"
    'INSTALL_TOKEN' => '', // Temporario: apague depois de criar o administrador.
];
