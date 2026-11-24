// js/cart-utils.js
// ЕДИНСТВЕННОЕ место объявления cart во всём проекте
let cart = JSON.parse(localStorage.getItem('familylavka_cart')) || [];

function saveCart() {
    localStorage.setItem('familylavka_cart', JSON.stringify(cart));
}

function updateCartUI() {
    const cartItemsList = document.getElementById('cart-items-list');
    const cartEmptyMsg = document.getElementById('cart-empty-msg');
    const cartCountEl = document.getElementById('cart-count');
    const totalEl = document.getElementById('cart-total');

    if (!cartItemsList || !cartCountEl || !totalEl) {
        saveCart();
        return;
    }

    cartItemsList.innerHTML = '';
    let total = 0;

    if (cart.length === 0) {
        cartEmptyMsg.style.display = 'block';
    } else {
        cartEmptyMsg.style.display = 'none';
        cart.forEach((item, index) => {
            total += item.price * item.qty;
            const li = document.createElement('li');
            li.className = 'cart-item';
            li.innerHTML = `
                <img src="${item.image || 'images/placeholder.jpg'}" alt="${item.name}">
                <div class="cart-info">
                    <h4 style="margin:0 0 4px 0; color:#333;">${item.name}</h4>
                    <p style="margin:0; color:#7f8c8d; font-size:0.9rem;">${item.price.toLocaleString()} ₽ × ${item.qty}</p>
                </div>
                <div class="cart-qty-controls">
                    <button class="qty-btn" onclick="changeCartQty(${index}, -1)">−</button>
                    <span style="font-weight:600;">${item.qty}</span>
                    <button class="qty-btn" onclick="changeCartQty(${index}, 1)">+</button>
                </div>
                <button class="remove-btn" onclick="removeFromCart(${index})">×</button>
            `;
            cartItemsList.appendChild(li);
        });
    }

    cartCountEl.textContent = cart.reduce((sum, item) => sum + item.qty, 0);
    totalEl.textContent = total.toLocaleString() + ' ₽';
    saveCart();
}

window.changeCartQty = function(index, delta) {
    if (!cart[index]) return;
    cart[index].qty += delta;
    if (cart[index].qty <= 0) {
        removeFromCart(index);
    } else {
        updateCartUI();
    }
};

window.removeFromCart = function(index) {
    cart.splice(index, 1);
    updateCartUI();
};

function clearCart() {
    cart = [];
    updateCartUI();
}

updateCartUI();
