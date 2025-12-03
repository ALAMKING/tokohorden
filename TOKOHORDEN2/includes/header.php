/* ===========================
   TEMA WARNA TOKO KORDEN
   Cream – Beige – Gold – Brown
   =========================== */
:root {
    --cream: #F3E8D7;
    --beige: #E7D3B8;
    --gold: #D8A75A;
    --brown: #6A4F37;
    --dark-brown: #4a3828;
    --light-cream: #faf6f0;

    --radius: 14px;
    --shadow: 0 8px 20px rgba(0,0,0,0.06);
    --shadow-hover: 0 12px 25px rgba(0,0,0,0.12);
}

/* ============= GLOBAL ============= */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", Arial, sans-serif;
}

body {
    background: var(--light-cream);
    color: var(--brown);
    line-height: 1.6;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

/* ============= HEADER & NAVIGATION ============= */
.header {
    background: rgba(255,255,255,0.95);
    backdrop-filter: blur(10px);
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: var(--shadow);
}

.nav-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
}

.logo {
    font-size: 28px;
    font-weight: 700;
    color: var(--brown);
    text-decoration: none;
    display: flex;
    align-items: center;
}

.logo i {
    color: var(--gold);
    margin-right: 10px;
}

.logo span {
    color: var(--gold);
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 30px;
}

.nav-links a {
    text-decoration: none;
    color: var(--brown);
    font-weight: 500;
    transition: color 0.3s;
    position: relative;
}

.nav-links a:hover,
.nav-links a.active {
    color: var(--gold);
}

.nav-links a::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--gold);
    transition: width 0.3s;
}

.nav-links a:hover::after,
.nav-links a.active::after {
    width: 100%;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

.nav-action-btn {
    background: none;
    border: none;
    color: var(--brown);
    font-size: 18px;
    cursor: pointer;
    transition: color 0.3s;
    position: relative;
}

.nav-action-btn:hover {
    color: var(--gold);
}

.cart-count {
    position: absolute;
    top: -8px;
    right: -8px;
    background: var(--gold);
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-login {
    background: var(--gold);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-login:hover {
    background: var(--dark-brown);
    transform: translateY(-2px);
}

/* ============= BUTTONS ============= */
.btn {
    padding: 12px 25px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    text-align: center;
    justify-content: center;
}

.btn-primary {
    background: var(--gold);
    color: white;
    box-shadow: 0 4px 15px rgba(216,167,90,0.3);
}

.btn-primary:hover {
    background: var(--dark-brown);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(216,167,90,0.4);
}

.btn-secondary {
    background: transparent;
    color: var(--brown);
    border: 2px solid var(--gold);
}

.btn-secondary:hover {
    background: var(--gold);
    color: white;
}

.btn-sm {
    padding: 8px 15px;
    font-size: 14px;
}

/* ============= FORMS ============= */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    color: var(--dark-brown);
    font-weight: 500;
}

.form-input, .form-select, .form-textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--beige);
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
    background: white;
}

.form-input:focus, .form-select:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(216,167,90,0.1);
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

/* ============= CARDS ============= */
.card {
    background: white;
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid var(--cream);
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark-brown);
}

/* ============= PRODUCT CARDS ============= */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 25px;
}

.product-card {
    background: white;
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow);
    transition: all 0.3s;
    position: relative;
}

.product-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-hover);
}

.product-badge {
    position: absolute;
    top: 15px;
    left: 15px;
    background: var(--gold);
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    z-index: 2;
}

.product-badge.discount {
    background: #e74c3c;
}

.product-image {
    width: 100%;
    height: 250px;
    background: var(--cream);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    position: relative;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-info {
    padding: 20px;
}

.product-category {
    color: var(--gold);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 5px;
}

.product-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--dark-brown);
    margin-bottom: 10px;
    line-height: 1.3;
}

.product-description {
    color: var(--brown);
    font-size: 14px;
    margin-bottom: 15px;
    line-height: 1.5;
}

.product-price {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.current-price {
    font-size: 20px;
    font-weight: 700;
    color: var(--dark-brown);
}

.original-price {
    font-size: 16px;
    color: #999;
    text-decoration: line-through;
}

.discount-percent {
    background: #ffe6e6;
    color: #e74c3c;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}

.product-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    font-size: 12px;
    color: var(--brown);
}

.product-rating {
    display: flex;
    align-items: center;
    gap: 5px;
}

.stars {
    color: var(--gold);
}

.product-actions {
    display: flex;
    gap: 10px;
}

.btn-cart {
    flex: 1;
    background: var(--gold);
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-cart:hover {
    background: var(--dark-brown);
}

.btn-wishlist {
    width: 45px;
    height: 45px;
    background: var(--cream);
    border: none;
    border-radius: 8px;
    color: var(--brown);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-wishlist:hover {
    background: var(--gold);
    color: white;
}

/* ============= TABLES ============= */
.table-responsive {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--cream);
}

th {
    background: var(--cream);
    font-weight: 600;
    color: var(--dark-brown);
}

tr:hover {
    background: rgba(243,232,215,0.3);
}

/* ============= UTILITIES ============= */
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }

.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 10px; }
.mb-2 { margin-bottom: 20px; }
.mb-3 { margin-bottom: 30px; }

.mt-0 { margin-top: 0; }
.mt-1 { margin-top: 10px; }
.mt-2 { margin-top: 20px; }
.mt-3 { margin-top: 30px; }

.p-0 { padding: 0; }
.p-1 { padding: 10px; }
.p-2 { padding: 20px; }
.p-3 { padding: 30px; }

/* ============= RESPONSIVE ============= */
@media (max-width: 768px) {
    .nav-links {
        display: none;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .product-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    }
    
    .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
}

@media (max-width: 480px) {
    .product-grid {
        grid-template-columns: 1fr;
    }
    
    .nav-actions {
        gap: 10px;
    }
    
    .btn-login {
        padding: 8px 15px;
        font-size: 14px;
    }
}