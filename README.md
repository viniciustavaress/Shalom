# 🛍️ Bazar Shalom Online

E-commerce solidário da Comunidade Shalom para venda de roupas via WhatsApp.

---

## 📁 Estrutura de arquivos

```
bazar-shalom/
├── index.php       ← Página principal (catálogo)
├── admin.php       ← Painel administrativo
├── db.php          ← Banco de dados (SQLite)
├── bazar.db        ← Criado automaticamente na 1ª execução
├── uploads/        ← Imagens dos produtos (criada automaticamente)
└── README.md
```

---

## ⚙️ Requisitos

- PHP 7.4+ com extensão PDO e PDO_SQLITE
- Servidor web: Apache, Nginx ou PHP built-in server

---

## 🚀 Como usar

### Opção 1 — PHP Built-in Server (teste rápido)
```bash
cd bazar-shalom
php -S localhost:8080
# Acesse: http://localhost:8080
```

### Opção 2 — Apache / Nginx
Coloque a pasta `bazar-shalom/` dentro do `htdocs` (XAMPP) ou `www` (WAMP).

---

## 🔐 Acesso admin

- URL: `admin.php`
- Senha padrão: **shalom2024**
- ⚠️ Troque a senha nas **Configurações** após o primeiro acesso!

---

## 📱 Configurar WhatsApp

1. Acesse `admin.php`
2. Vá em **Configurações**
3. Informe o número no formato: `5511999999999`
   (55 = Brasil, 11 = DDD, + número)

---

## 🎨 Tecnologias

- **PHP** — Backend e banco de dados
- **SQLite** — Banco de dados (sem configuração)
- **Tailwind CSS** — Estilização (CDN)
- **Alpine.js** — Interatividade (CDN)
- **Font Awesome** — Ícones (CDN)
- **Google Fonts** — Nunito + Playfair Display

---

## 🛒 Como funciona o fluxo de compra

1. Cliente acessa o site e navega pelo catálogo
2. Adiciona peças ao carrinho (salvo no LocalStorage)
3. Clica em "Finalizar pedido"
4. Preenche nome e WhatsApp
5. Mensagem é gerada e enviada pelo WhatsApp automaticamente
6. Cliente retira as peças presencialmente no bazar

---

## 📦 Produtos de exemplo

O banco de dados vem com 9 produtos de exemplo que são inseridos automaticamente.
Você pode editar ou remover pelo painel admin.

---

Feito com ❤️ para a missão da Comunidade Shalom
