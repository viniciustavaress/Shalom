<?php
session_start();
require_once 'db.php';

$db = getDB();
$error   = '';
$success = '';

// ── Logout ──────────────────────────────────────────────
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Login ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $password     = $_POST['password'] ?? '';
    $storedHash   = getSetting('admin_password');
    if (password_verify($password, $storedHash)) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Senha incorreta. Tente novamente.';
    }
}

// ── Exige login ─────────────────────────────────────────
$loggedIn = !empty($_SESSION['admin_logged_in']);

if ($loggedIn) {

    // ── Salvar configurações ─────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
        setSetting('whatsapp_number', trim($_POST['whatsapp'] ?? ''));
        setSetting('bazar_address',  trim($_POST['address']  ?? ''));

        $newPass = trim($_POST['new_password'] ?? '');
        if ($newPass) {
            setSetting('admin_password', password_hash($newPass, PASSWORD_DEFAULT));
        }
        $success = 'Configurações salvas com sucesso!';
    }

    // ── Adicionar produto ────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_product') {
        $name     = trim($_POST['name']     ?? '');
        $category = trim($_POST['category'] ?? '');
        $size     = trim($_POST['size']     ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');
        $image    = '';

        if ($name && $category && $size && $price > 0) {
            // Upload da imagem
            if (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $filename = uniqid('product_') . '.' . $ext;
                    $dest     = __DIR__ . '/uploads/' . $filename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        $image = 'uploads/' . $filename;
                    }
                } else {
                    $error = 'Formato de imagem não suportado.';
                }
            }

            if (!$error) {
                $stmt = $db->prepare("INSERT INTO products (name, category, size, price, image, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $category, $size, $price, $image, $desc]);
                $success = "Produto \"$name\" adicionado com sucesso!";
            }
        } else {
            $error = 'Preencha todos os campos obrigatórios.';
        }
    }

    // ── Editar produto ───────────────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_product') {
        $id       = (int)($_POST['product_id'] ?? 0);
        $name     = trim($_POST['name']     ?? '');
        $category = trim($_POST['category'] ?? '');
        $size     = trim($_POST['size']     ?? '');
        $price    = (float)($_POST['price'] ?? 0);
        $desc     = trim($_POST['description'] ?? '');
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($id && $name && $category && $size && $price > 0) {
            // Busca imagem atual
            $cur = $db->prepare("SELECT image FROM products WHERE id = ?");
            $cur->execute([$id]);
            $curRow = $cur->fetch();
            $image  = $curRow['image'] ?? '';

            if (!empty($_FILES['image']['name'])) {
                $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $filename = uniqid('product_') . '.' . $ext;
                    $dest     = __DIR__ . '/uploads/' . $filename;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                        // Remove imagem antiga
                        if ($image && file_exists(__DIR__ . '/' . $image)) {
                            @unlink(__DIR__ . '/' . $image);
                        }
                        $image = 'uploads/' . $filename;
                    }
                }
            }

            $stmt = $db->prepare("UPDATE products SET name=?, category=?, size=?, price=?, image=?, description=?, active=? WHERE id=?");
            $stmt->execute([$name, $category, $size, $price, $image, $desc, $active, $id]);
            $success = "Produto atualizado com sucesso!";
        } else {
            $error = 'Preencha todos os campos obrigatórios.';
        }
    }

    // ── Remover produto ──────────────────────────────────
    if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
        $id  = (int)$_GET['delete'];
        $cur = $db->prepare("SELECT image FROM products WHERE id = ?");
        $cur->execute([$id]);
        $row = $cur->fetch();
        if ($row && $row['image'] && file_exists(__DIR__ . '/' . $row['image'])) {
            @unlink(__DIR__ . '/' . $row['image']);
        }
        $db->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $success = 'Produto removido.';
    }

    // ── Busca produto para edição ────────────────────────
    $editProduct = null;
    if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([(int)$_GET['edit']]);
        $editProduct = $stmt->fetch();
    }

    // Carrega todos os produtos
    $products = $db->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll();
    $whatsapp = getSetting('whatsapp_number');
    $address  = getSetting('bazar_address');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — Bazar Shalom Online</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Nunito', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        input, select, textarea {
            @apply border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full
                   focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition;
        }
    </style>
</head>

<body class="font-sans bg-gray-100 min-h-screen">

<?php if (!$loggedIn): ?>
<!-- ═══════════════════ TELA DE LOGIN ═══════════════════ -->
<div class="min-h-screen flex items-center justify-center px-4 bg-gradient-to-br from-orange-50 to-orange-100">
    <div class="bg-white rounded-3xl shadow-xl p-8 w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-orange-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                <i class="fa-solid fa-dove text-white text-3xl"></i>
            </div>
            <h1 class="text-2xl font-black text-gray-800">Painel Admin</h1>
            <p class="text-gray-400 text-sm mt-1">Bazar Shalom Online</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-600 rounded-xl px-4 py-3 text-sm font-semibold mb-4 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="login">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1.5">Senha de administrador</label>
                <input type="password" name="password" placeholder="••••••••"
                       class="border border-gray-200 rounded-xl px-4 py-3 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
                       required autofocus>
            </div>
            <button type="submit"
                    class="w-full bg-orange-600 hover:bg-orange-700 text-white font-black py-3 rounded-xl transition-colors flex items-center justify-center gap-2">
                <i class="fa-solid fa-right-to-bracket"></i> Entrar
            </button>
        </form>

        <p class="text-center text-xs text-gray-400 mt-6">
            Senha padrão: <code class="bg-gray-100 px-1 rounded font-mono">shalom2024</code>
        </p>
        <div class="text-center mt-3">
            <a href="index.php" class="text-xs text-orange-500 hover:underline">
                <i class="fa-solid fa-arrow-left mr-1"></i> Voltar ao site
            </a>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════ PAINEL ADMIN ═══════════════════ -->

<!-- Navbar admin -->
<header class="bg-white shadow-sm sticky top-0 z-20">
    <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-orange-600 flex items-center justify-center">
                <i class="fa-solid fa-dove text-white"></i>
            </div>
            <div>
                <span class="font-black text-gray-800 text-sm">Painel Admin</span>
                <span class="hidden sm:inline text-gray-400 text-xs ml-2">— Bazar Shalom Online</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="index.php" target="_blank"
               class="text-sm font-semibold text-orange-600 hover:text-orange-700 flex items-center gap-1">
                <i class="fa-solid fa-arrow-up-right-from-square text-xs"></i>
                <span class="hidden sm:inline">Ver site</span>
            </a>
            <a href="?logout" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-red-600 transition-colors">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span class="hidden sm:inline">Sair</span>
            </a>
        </div>
    </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-8" x-data="{ tab: '<?= $editProduct ? 'products' : 'products' ?>', editMode: <?= $editProduct ? 'true' : 'false' ?> }">

    <!-- Alertas -->
    <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-2xl px-4 py-3 text-sm font-semibold mb-6 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-green-500 text-lg"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-600 rounded-2xl px-4 py-3 text-sm font-semibold mb-6 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-lg"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 bg-white rounded-2xl p-1.5 shadow-sm w-fit">
        <button @click="tab = 'products'"
                :class="tab === 'products' ? 'bg-orange-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-2 rounded-xl text-sm font-bold transition-all">
            <i class="fa-solid fa-shirt mr-1.5"></i> Produtos
        </button>
        <button @click="tab = 'add'"
                :class="tab === 'add' ? 'bg-orange-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-2 rounded-xl text-sm font-bold transition-all">
            <i class="fa-solid fa-plus mr-1.5"></i> Adicionar
        </button>
        <button @click="tab = 'settings'"
                :class="tab === 'settings' ? 'bg-orange-600 text-white shadow' : 'text-gray-500 hover:text-gray-700'"
                class="px-5 py-2 rounded-xl text-sm font-bold transition-all">
            <i class="fa-solid fa-gear mr-1.5"></i> Configurações
        </button>
    </div>

    <!-- ─── TAB: PRODUTOS ─────────────────────────────────── -->
    <div x-show="tab === 'products'" x-cloak>
        <?php if ($editProduct): ?>
        <!-- Formulário de edição -->
        <div class="bg-white rounded-3xl shadow-sm p-6 mb-6">
            <h2 class="font-black text-xl text-gray-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-orange-500"></i>
                Editando: <?= htmlspecialchars($editProduct['name']) ?>
            </h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_product">
                <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Nome da peça *</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($editProduct['name']) ?>" required
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Categoria *</label>
                        <select name="category" required
                                class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 transition">
                            <option value="Feminino"  <?= $editProduct['category'] === 'Feminino'  ? 'selected' : '' ?>>Feminino</option>
                            <option value="Masculino" <?= $editProduct['category'] === 'Masculino' ? 'selected' : '' ?>>Masculino</option>
                            <option value="Infantil"  <?= $editProduct['category'] === 'Infantil'  ? 'selected' : '' ?>>Infantil</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Tamanho *</label>
                        <input type="text" name="size" value="<?= htmlspecialchars($editProduct['size']) ?>" placeholder="PP, P, M, G, GG, 2 anos..." required
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Preço (R$) *</label>
                        <input type="number" name="price" step="0.01" min="0.01" value="<?= $editProduct['price'] ?>" required
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Nova foto (opcional)</label>
                        <input type="file" name="image" accept="image/*"
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none transition file:mr-3 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 file:border-0 file:rounded-lg file:px-3 file:py-1">
                        <?php if ($editProduct['image']): ?>
                            <img src="<?= htmlspecialchars($editProduct['image']) ?>" class="mt-2 h-16 w-16 object-cover rounded-lg border">
                        <?php endif; ?>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Descrição</label>
                        <textarea name="description" rows="2"
                                  class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition resize-none"><?= htmlspecialchars($editProduct['description']) ?></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="active" id="active_check" value="1" <?= $editProduct['active'] ? 'checked' : '' ?>
                               class="w-4 h-4 accent-orange-600">
                        <label for="active_check" class="text-sm font-semibold text-gray-700">Produto ativo (visível no site)</label>
                    </div>
                </div>
                <div class="flex gap-3 mt-6">
                    <button type="submit"
                            class="bg-orange-600 hover:bg-orange-700 text-white font-black px-6 py-2.5 rounded-xl transition-colors flex items-center gap-2 text-sm">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar alterações
                    </button>
                    <a href="admin.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold px-6 py-2.5 rounded-xl transition-colors text-sm">
                        Cancelar
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Lista de produtos -->
        <div class="bg-white rounded-3xl shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex items-center justify-between">
                <h2 class="font-black text-xl text-gray-800">
                    <i class="fa-solid fa-shirt text-orange-500 mr-2"></i>
                    Produtos (<?= count($products) ?>)
                </h2>
            </div>

            <?php if (empty($products)): ?>
                <div class="text-center py-16 text-gray-400">
                    <i class="fa-solid fa-box-open text-5xl mb-4 block text-gray-200"></i>
                    <p class="font-semibold">Nenhum produto cadastrado ainda.</p>
                </div>
            <?php else: ?>
                <!-- Mobile: cards -->
                <div class="sm:hidden divide-y divide-gray-100">
                    <?php foreach ($products as $p): ?>
                    <div class="p-4 flex gap-3 items-start">
                        <div class="w-14 h-14 rounded-xl bg-orange-50 flex-shrink-0 overflow-hidden flex items-center justify-center">
                            <?php if ($p['image']): ?>
                                <img src="<?= htmlspecialchars($p['image']) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                                <i class="fa-solid fa-shirt text-orange-300 text-xl"></i>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-gray-800 text-sm truncate"><?= htmlspecialchars($p['name']) ?></p>
                            <p class="text-xs text-gray-400"><?= $p['category'] ?> · <?= htmlspecialchars($p['size']) ?></p>
                            <p class="text-orange-600 font-black text-sm">R$ <?= number_format($p['price'], 2, ',', '.') ?></p>
                            <?php if (!$p['active']): ?>
                                <span class="text-xs bg-red-100 text-red-600 px-2 py-0.5 rounded-full font-semibold">Inativo</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex flex-col gap-2 flex-shrink-0">
                            <a href="?edit=<?= $p['id'] ?>"
                               class="bg-blue-50 text-blue-600 hover:bg-blue-100 font-bold px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="?delete=<?= $p['id'] ?>"
                               onclick="return confirm('Remover este produto?')"
                               class="bg-red-50 text-red-600 hover:bg-red-100 font-bold px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                <i class="fa-solid fa-trash-can"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Desktop: tabela -->
                <div class="hidden sm:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-bold text-gray-500 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-3 text-left">Foto</th>
                                <th class="px-6 py-3 text-left">Nome</th>
                                <th class="px-6 py-3 text-left">Categoria</th>
                                <th class="px-6 py-3 text-left">Tamanho</th>
                                <th class="px-6 py-3 text-left">Preço</th>
                                <th class="px-6 py-3 text-left">Status</th>
                                <th class="px-6 py-3 text-left">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($products as $p): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-3">
                                    <div class="w-12 h-12 rounded-xl bg-orange-50 overflow-hidden flex items-center justify-center">
                                        <?php if ($p['image']): ?>
                                            <img src="<?= htmlspecialchars($p['image']) ?>" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <i class="fa-solid fa-shirt text-orange-300"></i>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-3 font-semibold text-gray-800 max-w-xs">
                                    <p class="truncate"><?= htmlspecialchars($p['name']) ?></p>
                                    <?php if ($p['description']): ?>
                                        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($p['description']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3">
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full
                                        <?= $p['category'] === 'Feminino' ? 'bg-pink-100 text-pink-600' :
                                           ($p['category'] === 'Masculino' ? 'bg-blue-100 text-blue-600' : 'bg-green-100 text-green-600') ?>">
                                        <?= $p['category'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-gray-600 font-semibold"><?= htmlspecialchars($p['size']) ?></td>
                                <td class="px-6 py-3 text-orange-600 font-black">R$ <?= number_format($p['price'], 2, ',', '.') ?></td>
                                <td class="px-6 py-3">
                                    <?php if ($p['active']): ?>
                                        <span class="text-xs font-bold bg-green-100 text-green-600 px-2.5 py-1 rounded-full">Ativo</span>
                                    <?php else: ?>
                                        <span class="text-xs font-bold bg-red-100 text-red-600 px-2.5 py-1 rounded-full">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex gap-2">
                                        <a href="?edit=<?= $p['id'] ?>"
                                           class="bg-blue-50 hover:bg-blue-100 text-blue-600 font-bold px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                            <i class="fa-solid fa-pen"></i> Editar
                                        </a>
                                        <a href="?delete=<?= $p['id'] ?>"
                                           onclick="return confirm('Tem certeza que deseja remover este produto?')"
                                           class="bg-red-50 hover:bg-red-100 text-red-600 font-bold px-3 py-1.5 rounded-lg text-xs flex items-center gap-1 transition-colors">
                                            <i class="fa-solid fa-trash-can"></i> Remover
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div><!-- /tab products -->


    <!-- ─── TAB: ADICIONAR ────────────────────────────────── -->
    <div x-show="tab === 'add'" x-cloak>
        <div class="bg-white rounded-3xl shadow-sm p-6">
            <h2 class="font-black text-xl text-gray-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-orange-500"></i>
                Adicionar Nova Roupa
            </h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="add_product">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Nome da peça *</label>
                        <input type="text" name="name" placeholder="Ex: Vestido Floral Rosa"
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Categoria *</label>
                        <select name="category" required
                                class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 transition">
                            <option value="">Selecione...</option>
                            <option value="Feminino">Feminino</option>
                            <option value="Masculino">Masculino</option>
                            <option value="Infantil">Infantil</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Tamanho *</label>
                        <input type="text" name="size" placeholder="PP, P, M, G, GG, 2 anos..."
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Preço (R$) *</label>
                        <input type="number" name="price" step="0.01" min="0.01" placeholder="0,00"
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition"
                               required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Foto da peça</label>
                        <input type="file" name="image" accept="image/*"
                               class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none transition
                                      file:mr-3 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 file:border-0 file:rounded-lg file:px-3 file:py-1 file:cursor-pointer">
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG ou WEBP. Tamanho máximo: 5MB</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-gray-600 mb-1.5">Descrição (opcional)</label>
                        <textarea name="description" rows="2" placeholder="Estado, cor, detalhes da peça..."
                                  class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition resize-none"></textarea>
                    </div>
                </div>
                <div class="pt-2">
                    <button type="submit"
                            class="bg-orange-600 hover:bg-orange-700 text-white font-black px-8 py-3 rounded-xl transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-plus"></i> Adicionar produto
                    </button>
                </div>
            </form>
        </div>
    </div><!-- /tab add -->


    <!-- ─── TAB: CONFIGURAÇÕES ────────────────────────────── -->
    <div x-show="tab === 'settings'" x-cloak>
        <div class="bg-white rounded-3xl shadow-sm p-6">
            <h2 class="font-black text-xl text-gray-800 mb-6 flex items-center gap-2">
                <i class="fa-solid fa-gear text-orange-500"></i>
                Configurações do bazar
            </h2>
            <form method="POST" class="space-y-4 max-w-lg">
                <input type="hidden" name="action" value="save_settings">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">
                        <i class="fa-brands fa-whatsapp text-green-500 mr-1"></i>
                        Número do WhatsApp (com DDI)
                    </label>
                    <input type="text" name="whatsapp"
                           value="<?= htmlspecialchars($whatsapp) ?>"
                           placeholder="5511999999999"
                           class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                    <p class="text-xs text-gray-400 mt-1">Formato: 55 + DDD + número. Ex: 5511999999999</p>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">
                        <i class="fa-solid fa-location-dot text-orange-500 mr-1"></i>
                        Endereço do bazar
                    </label>
                    <input type="text" name="address"
                           value="<?= htmlspecialchars($address) ?>"
                           placeholder="Rua da Missão, 123 - Centro"
                           class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                </div>
                <div class="pt-2 border-t border-gray-100">
                    <label class="block text-xs font-bold text-gray-600 mb-1.5">
                        <i class="fa-solid fa-lock text-gray-400 mr-1"></i>
                        Nova senha do admin (deixe em branco para não alterar)
                    </label>
                    <input type="password" name="new_password" placeholder="Nova senha..."
                           class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm w-full focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
                </div>
                <div class="pt-2">
                    <button type="submit"
                            class="bg-orange-600 hover:bg-orange-700 text-white font-black px-8 py-3 rounded-xl transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar configurações
                    </button>
                </div>
            </form>
        </div>
    </div><!-- /tab settings -->

</div><!-- /container -->

<?php endif; ?>
</body>
</html>
