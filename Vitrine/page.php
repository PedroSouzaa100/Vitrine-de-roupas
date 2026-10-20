<?php
require_once __DIR__ . '/includes/auth.php';

$slug = trim($_GET['slug'] ?? 'sobre');

$pages = [
  'sobre' => [
    'title' => 'Sobre a Vitrine',
    'content' => '<p>A Vitrine é uma plataforma que conecta clientes a lojas de moda de todo o Brasil. Aqui você encontra produtos masculinos, femininos e unissex de vendedores verificados.</p>
                  <h5 class="mt-4">Nossa missão</h5>
                  <p>Facilitar a descoberta de lojas independentes e dar visibilidade a pequenos empreendedores da moda.</p>
                  <h5 class="mt-4">Para vendedores</h5>
                  <p>Crie sua loja gratuitamente, cadastre produtos com fotos, tamanhos, cores e receba contatos direto pelo WhatsApp.</p>'
  ],
  'termos' => [
    'title' => 'Termos de Uso',
    'content' => '<p>Ao usar a plataforma Vitrine, você concorda com os termos abaixo.</p>
                  <h5>1. Cadastro</h5><p>Você é responsável pela veracidade das informações fornecidas.</p>
                  <h5>2. Vendedores</h5><p>É proibido cadastrar produtos ilegais, falsificados ou que violem direitos autorais.</p>
                  <h5>3. Conteúdo</h5><p>A Vitrine não é responsável por negociações diretas entre clientes e vendedores.</p>
                  <h5>4. Suspensão</h5><p>Contas que violarem estes termos podem ser suspensas ou removidas.</p>'
  ],
  'privacidade' => [
    'title' => 'Política de Privacidade',
    'content' => '<p>Levamos sua privacidade a sério.</p>
                  <h5>Dados coletados</h5><p>Nome, e-mail, telefone e dados da loja (para vendedores).</p>
                  <h5>Uso dos dados</h5><p>Utilizamos os dados para exibir lojas, permitir contato via WhatsApp e melhorar a plataforma.</p>
                  <h5>Compartilhamento</h5><p>Não vendemos seus dados. Eles são usados apenas dentro da plataforma.</p>
                  <h5>Seus direitos</h5><p>Você pode solicitar exclusão dos seus dados a qualquer momento.</p>'
  ],
];

$page = $pages[$slug] ?? $pages['sobre'];

$pageTitle = $page['title'];
$metaDescription = excerpt(strip_tags($page['content']), 160);
require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-9">
      <div class="form-card">
        <h1 class="h3 fw-bold mb-4"><?= e($page['title']) ?></h1>
        <div class="content-page"><?= $page['content'] ?></div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
