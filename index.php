<?php
require_once 'db.php';

$db = getDB();
$whatsapp = getSetting('whatsapp_number');
$address  = getSetting('bazar_address');

// Fetch active products
$products = $db->query("SELECT * FROM products WHERE active = 1 ORDER BY created_at DESC")->fetchAll();

// JSON for Alpine.js
$productsJson = json_encode(array_map(function($p) {
    return [
        'id'          => (int)$p['id'],
        'name'        => $p['name'],
        'category'    => $p['category'],
        'size'        => $p['size'],
        'price'       => (float)$p['price'],
        'image'       => $p['image'],
        'description' => $p['description'],
    ];
}, $products));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bazar Shalom Online</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        orange: {
                            50:  '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                        }
                    },
                    fontFamily: {
                        sans:    ['Nunito', 'sans-serif'],
                        display: ['"Playfair Display"', 'serif'],
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }

        /* Scrollbar oculta no carrinho */
        .cart-scroll::-webkit-scrollbar { width: 4px; }
        .cart-scroll::-webkit-scrollbar-track { background: #f1f1f1; }
        .cart-scroll::-webkit-scrollbar-thumb { background: #ea580c; border-radius: 2px; }

        /* Hero pattern */
        .hero-pattern {
            background-color: #ea580c;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.07'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        /* Card hover */
        .product-card { transition: transform .2s ease, box-shadow .2s ease; }
        .product-card:hover { transform: translateY(-4px); box-shadow: 0 12px 28px rgba(0,0,0,.12); }

        /* Badge animation */
        @keyframes pop { 0%,100%{transform:scale(1)} 50%{transform:scale(1.3)} }
        .badge-pop { animation: pop .3s ease; }

        /* Slide-in sidebar */
        .cart-sidebar { transition: transform .3s ease; }
        .cart-sidebar.open { transform: translateX(0); }
        .cart-sidebar.closed { transform: translateX(100%); }

        /* Overlay */
        .overlay { transition: opacity .3s ease; }
    </style>
</head>

<body class="font-sans bg-gray-50 text-gray-800" x-data="bazarApp()" x-init="init()">

<!-- ───────────────────── OVERLAY ───────────────────── -->
<div class="overlay fixed inset-0 bg-black/50 z-40"
     :class="cartOpen || checkoutOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'"
     @click="cartOpen = false; checkoutOpen = false"></div>

<!-- ═══════════════════ NAVBAR ═══════════════════ -->
<header class="sticky top-0 z-30 bg-white shadow-sm">
    <div class="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">

        <!-- Logo + Nome -->
        <a href="#" class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-orange-600 flex items-center justify-center shadow">
                <i class="fa-solid fa-dove text-white text-lg"></i>
            </div>
            <div class="leading-tight">
                <span class="block text-orange-600 font-black text-base tracking-tight">Bazar Shalom</span>
                <span class="block text-gray-400 text-[11px] font-semibold uppercase tracking-wider">Online</span>
            </div>
        </a>

        <!-- Nav links (desktop) -->
        <nav class="hidden md:flex items-center gap-6 text-sm font-semibold text-gray-600">
            <a href="#inicio"      class="hover:text-orange-600 transition-colors">Início</a>
            <a href="#roupas"      class="hover:text-orange-600 transition-colors">Roupas</a>
            <a href="#como-comprar" class="hover:text-orange-600 transition-colors">Como comprar</a>
            <a href="#contato"     class="hover:text-orange-600 transition-colors">Contato</a>
        </nav>

        <!-- Carrinho -->
        <button @click="cartOpen = true"
                class="relative flex items-center gap-2 bg-orange-600 hover:bg-orange-700 text-white font-bold px-4 py-2 rounded-full text-sm transition-colors shadow">
            <i class="fa-solid fa-cart-shopping"></i>
            <span class="hidden sm:inline">Carrinho</span>
            <span x-show="totalItems > 0"
                  x-text="totalItems"
                  :class="{'badge-pop': badgePop}"
                  class="absolute -top-2 -right-2 bg-yellow-400 text-orange-900 text-xs font-black w-5 h-5 rounded-full flex items-center justify-center">
            </span>
        </button>
    </div>

    <!-- Mobile nav -->
    <nav class="md:hidden flex items-center justify-center gap-5 py-2 border-t border-gray-100 text-xs font-bold text-gray-500">
        <a href="#inicio"       class="hover:text-orange-600 transition-colors">Início</a>
        <a href="#roupas"       class="hover:text-orange-600 transition-colors">Roupas</a>
        <a href="#como-comprar" class="hover:text-orange-600 transition-colors">Como comprar</a>
        <a href="#contato"      class="hover:text-orange-600 transition-colors">Contato</a>
    </nav>
</header>


<!-- ═══════════════════ HERO ═══════════════════ -->
<section id="inicio" class="hero-pattern text-white py-20 md:py-28 px-4">
    <div class="max-w-4xl mx-auto text-center">
        <span class="inline-block bg-white/20 text-white text-xs font-bold uppercase tracking-widest px-4 py-1 rounded-full mb-5">
            <i class="fa-solid fa-heart mr-1"></i> Bazar Solidário
        </span>
        <h1 class="font-display text-4xl md:text-6xl font-extrabold leading-tight mb-5">
            Achadinhos que ajudam<br>na missão
        </h1>
        <p class="text-orange-100 text-lg md:text-xl max-w-2xl mx-auto mb-8 leading-relaxed">
            Cada peça vendida aqui contribui para a missão evangelizadora da
            <strong class="text-white">Comunidade Shalom</strong>.
            Compre com amor e faça o bem! 💛
        </p>
        <a href="#roupas"
           class="inline-flex items-center gap-2 bg-white text-orange-600 font-black px-8 py-4 rounded-full text-base shadow-lg hover:bg-orange-50 transition-colors">
            <i class="fa-solid fa-shirt"></i> Ver roupas disponíveis
        </a>
    </div>
</section>


<!-- ═══════════════════ COMO COMPRAR ═══════════════════ -->
<section id="como-comprar" class="bg-white py-14 px-4">
    <div class="max-w-5xl mx-auto">
        <h2 class="font-display text-2xl md:text-3xl font-bold text-center text-gray-800 mb-10">
            Como comprar? É simples! 🛍️
        </h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="text-center p-6 bg-orange-50 rounded-2xl">
                <div class="w-14 h-14 bg-orange-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <h3 class="font-black text-gray-800 mb-2">1. Escolha as peças</h3>
                <p class="text-gray-500 text-sm">Navegue pelo catálogo, filtre por categoria e adicione ao carrinho.</p>
            </div>
            <div class="text-center p-6 bg-orange-50 rounded-2xl">
                <div class="w-14 h-14 bg-orange-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <h3 class="font-black text-gray-800 mb-2">2. Envie pelo WhatsApp</h3>
                <p class="text-gray-500 text-sm">Clique em "Finalizar pedido" e envie sua lista automaticamente.</p>
            </div>
            <div class="text-center p-6 bg-orange-50 rounded-2xl">
                <div class="w-14 h-14 bg-orange-600 text-white rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl shadow">
                    <i class="fa-solid fa-location-dot"></i>
                </div>
                <h3 class="font-black text-gray-800 mb-2">3. Retire no bazar</h3>
                <p class="text-gray-500 text-sm">Combine o horário e venha buscar sua roupa presencialmente.</p>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════ CATÁLOGO ═══════════════════ -->
<section id="roupas" class="py-14 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">

        <h2 class="font-display text-2xl md:text-3xl font-bold text-center text-gray-800 mb-2">
            Peças disponíveis
        </h2>
        <p class="text-center text-gray-500 mb-8 text-sm">
            <i class="fa-solid fa-circle-check text-green-500"></i>
            Todas as peças estão em bom estado de conservação
        </p>

        <!-- Filtros -->
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            <button @click="filter = 'Todos'"
                    :class="filter === 'Todos' ? 'bg-orange-600 text-white shadow' : 'bg-white text-gray-600 hover:border-orange-300'"
                    class="px-5 py-2 rounded-full text-sm font-bold border border-gray-200 transition-all">
                <i class="fa-solid fa-layer-group mr-1"></i> Todos
            </button>
            <button @click="filter = 'Feminino'"
                    :class="filter === 'Feminino' ? 'bg-orange-600 text-white shadow' : 'bg-white text-gray-600 hover:border-orange-300'"
                    class="px-5 py-2 rounded-full text-sm font-bold border border-gray-200 transition-all">
                <i class="fa-solid fa-venus mr-1"></i> Feminino
            </button>
            <button @click="filter = 'Masculino'"
                    :class="filter === 'Masculino' ? 'bg-orange-600 text-white shadow' : 'bg-white text-gray-600 hover:border-orange-300'"
                    class="px-5 py-2 rounded-full text-sm font-bold border border-gray-200 transition-all">
                <i class="fa-solid fa-mars mr-1"></i> Masculino
            </button>
            <button @click="filter = 'Infantil'"
                    :class="filter === 'Infantil' ? 'bg-orange-600 text-white shadow' : 'bg-white text-gray-600 hover:border-orange-300'"
                    class="px-5 py-2 rounded-full text-sm font-bold border border-gray-200 transition-all">
                <i class="fa-solid fa-child mr-1"></i> Infantil
            </button>
        </div>

        <!-- Contagem -->
        <p class="text-center text-sm text-gray-400 mb-6">
            <span x-text="filteredProducts.length"></span> peça(s) encontrada(s)
        </p>

        <!-- Grid de produtos -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <template x-for="product in filteredProducts" :key="product.id">
                <div class="product-card bg-white rounded-2xl overflow-hidden shadow-sm border border-gray-100 flex flex-col">
                    <!-- Imagem -->
                    <div class="relative aspect-square bg-orange-50 overflow-hidden">
                        <img x-show="product.image"
                             :src="product.image"
                             :alt="product.name"
                             class="w-full h-full object-cover"
                             @error="$el.style.display='none'">
                        <div x-show="!product.image"
                             class="absolute inset-0 flex flex-col items-center justify-center text-orange-300">
                            <i class="fa-solid fa-shirt text-4xl mb-2"></i>
                            <span class="text-xs font-semibold" x-text="product.category"></span>
                        </div>
                        <!-- Badge categoria -->
                        <span class="absolute top-2 left-2 text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                              :class="{
                                'bg-pink-100 text-pink-600':   product.category === 'Feminino',
                                'bg-blue-100 text-blue-600':   product.category === 'Masculino',
                                'bg-green-100 text-green-600': product.category === 'Infantil'
                              }"
                              x-text="product.category">
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-3 flex flex-col flex-1">
                        <h3 class="font-bold text-gray-800 text-sm leading-snug mb-1" x-text="product.name"></h3>
                        <p class="text-gray-400 text-xs mb-2">
                            <i class="fa-solid fa-ruler-horizontal mr-1"></i>
                            Tamanho: <span class="font-semibold text-gray-600" x-text="product.size"></span>
                        </p>
                        <p x-show="product.description" class="text-gray-400 text-xs mb-2 line-clamp-2" x-text="product.description"></p>
                        <div class="mt-auto">
                            <p class="text-orange-600 font-black text-lg mb-2"
                               x-text="'R$ ' + product.price.toFixed(2).replace('.', ',')"></p>
                            <button @click="addToCart(product)"
                                    class="w-full bg-orange-600 hover:bg-orange-700 active:scale-95 text-white font-bold py-2 rounded-xl text-xs transition-all flex items-center justify-center gap-1">
                                <i class="fa-solid fa-cart-plus"></i> Adicionar
                            </button>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Sem resultados -->
            <div x-show="filteredProducts.length === 0"
                 class="col-span-full text-center py-20 text-gray-400">
                <i class="fa-solid fa-box-open text-5xl mb-4 block"></i>
                <p class="font-semibold">Nenhuma peça nessa categoria no momento.</p>
            </div>
        </div>
    </div>
</section>


<!-- ═══════════════════ CARRINHO (Sidebar) ═══════════════════ -->
<aside class="cart-sidebar fixed top-0 right-0 h-full w-full sm:w-96 bg-white z-50 shadow-2xl flex flex-col"
       :class="cartOpen ? 'open' : 'closed'">

    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-100 bg-orange-600 text-white">
        <div class="flex items-center gap-2 font-black text-lg">
            <i class="fa-solid fa-cart-shopping"></i> Carrinho
            <span x-show="totalItems > 0"
                  class="bg-yellow-400 text-orange-900 text-xs font-black w-5 h-5 rounded-full flex items-center justify-center"
                  x-text="totalItems"></span>
        </div>
        <button @click="cartOpen = false" class="hover:bg-orange-700 rounded-full w-8 h-8 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>

    <!-- Items -->
    <div class="flex-1 overflow-y-auto cart-scroll p-4 space-y-3">
        <template x-if="cart.length === 0">
            <div class="text-center py-16 text-gray-400">
                <i class="fa-solid fa-cart-shopping text-5xl mb-4 block text-gray-200"></i>
                <p class="font-semibold">Seu carrinho está vazio</p>
                <p class="text-sm mt-1">Adicione peças para começar!</p>
            </div>
        </template>

        <template x-for="(item, index) in cart" :key="item.id">
            <div class="flex gap-3 bg-gray-50 rounded-xl p-3 items-start">
                <!-- Imagem mini -->
                <div class="w-14 h-14 rounded-lg bg-orange-100 overflow-hidden flex-shrink-0 flex items-center justify-center">
                    <img x-show="item.image" :src="item.image" :alt="item.name"
                         class="w-full h-full object-cover" @error="$el.style.display='none'">
                    <i x-show="!item.image" class="fa-solid fa-shirt text-orange-300 text-xl"></i>
                </div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-sm text-gray-800 truncate" x-text="item.name"></p>
                    <p class="text-xs text-gray-400">Tam: <span x-text="item.size"></span></p>
                    <p class="text-orange-600 font-black text-sm mt-0.5"
                       x-text="'R$ ' + (item.price * item.qty).toFixed(2).replace('.', ',')"></p>
                </div>
                <!-- Qty + Remove -->
                <div class="flex flex-col items-end gap-2 flex-shrink-0">
                    <button @click="removeFromCart(index)"
                            class="text-red-400 hover:text-red-600 transition-colors w-6 h-6 flex items-center justify-center">
                        <i class="fa-solid fa-trash-can text-xs"></i>
                    </button>
                    <div class="flex items-center gap-1 bg-white rounded-full border border-gray-200">
                        <button @click="decreaseQty(index)"
                                class="w-6 h-6 flex items-center justify-center text-orange-600 hover:bg-orange-50 rounded-full transition-colors font-black">
                            −
                        </button>
                        <span class="text-xs font-bold w-4 text-center" x-text="item.qty"></span>
                        <button @click="increaseQty(index)"
                                class="w-6 h-6 flex items-center justify-center text-orange-600 hover:bg-orange-50 rounded-full transition-colors font-black">
                            +
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Footer -->
    <div class="border-t border-gray-100 p-4 space-y-3">
        <div class="flex justify-between items-center">
            <span class="text-gray-500 font-semibold">Total:</span>
            <span class="text-orange-600 font-black text-2xl"
                  x-text="'R$ ' + totalPrice.toFixed(2).replace('.', ',')"></span>
        </div>
        <button @click="cart.length > 0 && (cartOpen = false, checkoutOpen = true)"
                :disabled="cart.length === 0"
                class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-300 text-white font-black py-3 rounded-xl transition-colors flex items-center justify-center gap-2 text-base">
            <i class="fa-brands fa-whatsapp text-xl"></i> Finalizar pedido
        </button>
        <button @click="cartOpen = false"
                class="w-full text-gray-400 text-sm font-semibold py-2 hover:text-gray-600 transition-colors">
            Continuar comprando
        </button>
    </div>
</aside>


<!-- ═══════════════════ MODAL CHECKOUT ═══════════════════ -->
<div class="fixed inset-0 z-50 flex items-center justify-center px-4"
     x-show="checkoutOpen" x-cloak>
    <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md p-6 relative" @click.stop>

        <button @click="checkoutOpen = false"
                class="absolute top-4 right-4 text-gray-300 hover:text-gray-500 transition-colors">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>

        <div class="text-center mb-6">
            <div class="w-14 h-14 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <i class="fa-brands fa-whatsapp text-green-600 text-3xl"></i>
            </div>
            <h2 class="font-display text-2xl font-bold text-gray-800">Finalizar Pedido</h2>
            <p class="text-gray-400 text-sm mt-1">Seus dados serão enviados pelo WhatsApp</p>
        </div>

        <!-- Resumo rápido -->
        <div class="bg-orange-50 rounded-2xl p-3 mb-4">
            <p class="text-xs font-bold text-orange-600 uppercase tracking-wider mb-2">Resumo do pedido</p>
            <template x-for="item in cart" :key="item.id">
                <div class="flex justify-between text-sm py-1">
                    <span class="text-gray-700" x-text="item.name + ' (x' + item.qty + ')'"></span>
                    <span class="font-bold text-gray-800"
                          x-text="'R$ ' + (item.price * item.qty).toFixed(2).replace('.', ',')"></span>
                </div>
            </template>
            <div class="flex justify-between font-black text-orange-600 border-t border-orange-200 mt-2 pt-2">
                <span>Total</span>
                <span x-text="'R$ ' + totalPrice.toFixed(2).replace('.', ',')"></span>
            </div>
        </div>

        <!-- Formulário -->
        <div class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Seu nome *</label>
                <input x-model="form.name" type="text" placeholder="Ex: Maria da Silva"
                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">WhatsApp *</label>
                <input x-model="form.phone" type="tel" placeholder="Ex: (11) 99999-9999"
                       class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">Observações</label>
                <textarea x-model="form.obs" rows="2" placeholder="Alguma observação sobre o pedido?"
                          class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100 transition resize-none"></textarea>
            </div>
        </div>

        <!-- Erro -->
        <p x-show="formError" class="text-red-500 text-xs font-semibold mt-2" x-text="formError"></p>

        <button @click="sendWhatsApp()"
                class="w-full mt-4 bg-green-600 hover:bg-green-700 text-white font-black py-4 rounded-2xl transition-colors flex items-center justify-center gap-2 text-base shadow-lg">
            <i class="fa-brands fa-whatsapp text-xl"></i> Enviar pedido pelo WhatsApp
        </button>

        <p class="text-center text-xs text-gray-400 mt-3">
            <i class="fa-solid fa-map-marker-alt text-orange-400 mr-1"></i>
            Retirada: <?= htmlspecialchars($address) ?>
        </p>
    </div>
</div>


<!-- ═══════════════════ CONTATO ═══════════════════ -->
<section id="contato" class="bg-white py-14 px-4">
    <div class="max-w-2xl mx-auto text-center">
        <h2 class="font-display text-2xl md:text-3xl font-bold text-gray-800 mb-4">
            Fale conosco
        </h2>
        <p class="text-gray-500 mb-6">Dúvidas? Entre em contato pelo WhatsApp!</p>
        <a href="https://wa.me/<?= $whatsapp ?>?text=Olá!%20Tenho%20uma%20dúvida%20sobre%20o%20Bazar%20Shalom%20Online."
           target="_blank"
           class="inline-flex items-center gap-3 bg-green-600 hover:bg-green-700 text-white font-black px-8 py-4 rounded-full text-base shadow-lg transition-colors">
            <i class="fa-brands fa-whatsapp text-2xl"></i>
            Chamar no WhatsApp
        </a>
        <p class="text-gray-400 text-sm mt-6">
            <i class="fa-solid fa-location-dot text-orange-500 mr-1"></i>
            <?= htmlspecialchars($address) ?>
        </p>
    </div>
</section>


<!-- ═══════════════════ RODAPÉ ═══════════════════ -->
<footer class="bg-gray-900 text-white py-10 px-4">
    <div class="max-w-6xl mx-auto">
        <div class="flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-600 flex items-center justify-center">
                    <i class="fa-solid fa-dove text-white"></i>
                </div>
                <div>
                    <p class="font-black text-white">Bazar Solidário da Comunidade Shalom</p>
                    <p class="text-gray-400 text-xs">Evangelizar, servir, transformar</p>
                </div>
            </div>

            <a href="https://wa.me/<?= $whatsapp ?>?text=Olá!%20Vi%20o%20Bazar%20Shalom%20Online."
               target="_blank"
               class="flex items-center gap-2 bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 rounded-full text-sm transition-colors">
                <i class="fa-brands fa-whatsapp text-lg"></i> Contato WhatsApp
            </a>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-800 text-center text-gray-500 text-xs">
            <p>© <?= date('Y') ?> Bazar Shalom Online — Feito com <i class="fa-solid fa-heart text-orange-500"></i> para a missão</p>
        </div>
    </div>
</footer>


<!-- ═══════════════════ ALPINE.JS APP ═══════════════════ -->
<script>
function bazarApp() {
    return {
        allProducts: <?= $productsJson ?>,
        filter:      'Todos',
        cart:        JSON.parse(localStorage.getItem('bazarCart') || '[]'),
        cartOpen:    false,
        checkoutOpen: false,
        badgePop:    false,
        form: { name: '', phone: '', obs: '' },
        formError: '',
        whatsappNumber: '<?= $whatsapp ?>',

        init() {
            this.$watch('cart', val => {
                localStorage.setItem('bazarCart', JSON.stringify(val));
            });
        },

        get filteredProducts() {
            if (this.filter === 'Todos') return this.allProducts;
            return this.allProducts.filter(p => p.category === this.filter);
        },

        get totalItems() {
            return this.cart.reduce((s, i) => s + i.qty, 0);
        },

        get totalPrice() {
            return this.cart.reduce((s, i) => s + i.price * i.qty, 0);
        },

        addToCart(product) {
            const idx = this.cart.findIndex(i => i.id === product.id);
            if (idx >= 0) {
                this.cart[idx].qty++;
                this.cart = [...this.cart];
            } else {
                this.cart = [...this.cart, { ...product, qty: 1 }];
            }
            this.badgePop = true;
            setTimeout(() => this.badgePop = false, 300);
        },

        removeFromCart(index) {
            this.cart = this.cart.filter((_, i) => i !== index);
        },

        increaseQty(index) {
            this.cart[index].qty++;
            this.cart = [...this.cart];
        },

        decreaseQty(index) {
            if (this.cart[index].qty > 1) {
                this.cart[index].qty--;
                this.cart = [...this.cart];
            } else {
                this.removeFromCart(index);
            }
        },

        sendWhatsApp() {
            this.formError = '';
            if (!this.form.name.trim()) { this.formError = 'Por favor, informe seu nome.'; return; }
            if (!this.form.phone.trim()) { this.formError = 'Por favor, informe seu WhatsApp.'; return; }

            let itens = this.cart.map(i =>
                `• ${i.name} (Tam: ${i.size}) × ${i.qty} — R$ ${(i.price * i.qty).toFixed(2).replace('.', ',')}`
            ).join('\n');

            let total = 'R$ ' + this.totalPrice.toFixed(2).replace('.', ',');

            let obs = this.form.obs.trim() ? `\nObservações: ${this.form.obs.trim()}` : '';

            let msg =
`🛍️ *Novo Pedido — Bazar Shalom Online*

👤 *Nome:* ${this.form.name.trim()}
📱 *WhatsApp:* ${this.form.phone.trim()}

📦 *Pedido:*
${itens}

💰 *Total: ${total}*${obs}

✅ Vou buscar no bazar!`;

            const url = `https://wa.me/${this.whatsappNumber}?text=${encodeURIComponent(msg)}`;
            window.open(url, '_blank');

            // Limpa carrinho após envio
            this.cart = [];
            this.checkoutOpen = false;
            this.form = { name: '', phone: '', obs: '' };
        }
    }
}
</script>

</body>
</html>
