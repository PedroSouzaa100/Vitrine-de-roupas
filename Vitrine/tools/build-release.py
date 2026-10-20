"""Gera pacote de publicacao sem credenciais, logs ou SQL destrutivo."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib
root = Path(__file__).resolve().parent.parent
output = root / 'dist'
output.mkdir(exist_ok=True)
archive = output / 'vitrine-hostinger.zip'
allowed_dirs = {'admin', 'assets', 'config', 'includes', 'seller', 'uploads', 'tools'}
with ZipFile(archive, 'w', ZIP_DEFLATED) as bundle:
    for file in sorted(root.rglob('*')):
        if not file.is_file(): continue
        relative = file.relative_to(root)
        if len(relative.parts) > 1 and relative.parts[0] not in allowed_dirs: continue
        if any(part.startswith('.') for part in relative.parts) and file.name not in {'.htaccess', '.user.ini'}: continue
        if relative.as_posix() in {'config/local.php', 'config/development.php', 'database.sql', 'tools/build-release.py'}: continue
        if len(relative.parts) == 1 and file.suffix not in {'.php', '.md', '.sql'} and file.name not in {'.htaccess', '.user.ini'}: continue
        if file.suffix.lower() in {'.log', '.bak', '.zip', '.pyc'}: continue
        bundle.write(file, relative.as_posix())
    bundle.writestr('logs/.htaccess', 'Require all denied\n')
    bundle.writestr('uploads/stores/', '')
    bundle.writestr('uploads/products/', '')
with ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert 'config/local.php' not in bundle.namelist()
    assert 'database.sql' not in bundle.namelist()
    assert 'index.php' in bundle.namelist()
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(output / 'vitrine-hostinger.sha256').write_text(digest + '  vitrine-hostinger.zip\n', encoding='utf-8')
print(f'Pacote: {archive} ({archive.stat().st_size} bytes)')
