<?php
require_once __DIR__ . '/includes/header.php';

$pdo = db();
$errors = [];

// Criar / editar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id   = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $type = in_array($_POST['type'] ?? '', ['gender','category'], true) ? $_POST['type'] : 'category';
    $sort = (int) ($_POST['sort_order'] ?? 0);

    if (mb_strlen($name) < 2) $errors[] = 'Nome muito curto.';

    if (!$errors) {
        $slugBase = slugify($name);
        $slug = unique_slug('categories', $slugBase, $id ?: null);

        if ($id) {
            $pdo->prepare("UPDATE categories SET name=?, slug=?, type=?, sort_order=? WHERE id=?")
                ->execute([$name, $slug, $type, $sort, $id]);
            log_admin('update_category', 'category', $id);
            set_flash('success', 'Categoria atualizada.');
        } else {
            $pdo->prepare("INSERT INTO categories (name, slug, type, sort_order) VALUES (?,?,?,?)")
                ->execute([$name, $slug, $type, $sort]);
            log_admin('create_category', 'category');
            set_flash('success', 'Categoria criada.');
        }
        redirect('admin/categories.php');
    }
}

// Excluir
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int) $_POST['delete_id']]);
    log_admin('delete_category', 'category', (int) $_POST['delete_id']);
    set_flash('success', 'Categoria removida.');
    redirect('admin/categories.php');
}

// Editar (abrir form)
$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $st = $pdo->prepare("SELECT * FROM categories WHERE id=?");
    $st->execute([$editId]);
    $edit = $st->fetch();
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY type, sort_order, name")->fetchAll();
?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $e_): ?><div><?= e($e_) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="form-card">
      <h2 class="h6 fw-bold mb-3"><?= $edit ? 'Editar' : 'Nova' ?> categoria</h2>
      <form method="post">
        <?= csrf_field() ?>
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="mb-3">
          <label class="form-label">Nome</label>
          <input type="text" name="name" class="form-control" value="<?= e($edit['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Tipo</label>
          <select name="type" class="form-select">
            <option value="category" <?= ($edit['type'] ?? '') === 'category' ? 'selected' : '' ?>>Categoria</option>
            <option value="gender"   <?= ($edit['type'] ?? '') === 'gender' ? 'selected' : '' ?>>Público (gênero)</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Ordem</label>
          <input type="number" name="sort_order" class="form-control" value="<?= (int) ($edit['sort_order'] ?? 0) ?>">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-brand flex-fill"><?= $edit ? 'Salvar' : 'Criar' ?></button>
          <?php if ($edit): ?>
            <a class="btn btn-outline-brand" href="<?= base_url('admin/categories.php') ?>">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="table-card">
      <div class="table-responsive">
        <table class="table align-middle">
          <thead><tr><th>Nome</th><th>Tipo</th><th>Slug</th><th>Ordem</th><th class="text-end"></th></tr></thead>
          <tbody>
          <?php foreach ($categories as $c): ?>
            <tr>
              <td class="fw-semibold"><?= e($c['name']) ?></td>
              <td><span class="badge text-bg-light"><?= $c['type'] === 'gender' ? 'Gênero' : 'Categoria' ?></span></td>
              <td class="text-muted small"><?= e($c['slug']) ?></td>
              <td><?= (int) $c['sort_order'] ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-icon" href="?edit=<?= (int) $c['id'] ?>"><i class="fa-solid fa-pen"></i></a>
                <form method="post" class="d-inline" onsubmit="return confirm('Excluir esta categoria?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="delete_id" value="<?= (int) $c['id'] ?>">
                  <button class="btn btn-sm btn-icon"><i class="fa-regular fa-trash-can"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
