// Main JavaScript file for Luxury Living

class LuxuryLiving {
    constructor() {
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.updateCartCount();
        this.setupMobileMenu();
    }

    // Setup event listeners
    setupEventListeners() {
        // Cart functionality
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn-cart')) {
                this.addToCart(e);
            }
            
            if (e.target.closest('.btn-wishlist')) {
                this.toggleWishlist(e);
            }
        });

        // Search functionality
        const searchBtn = document.querySelector('.nav-action-btn .fa-search');
        if (searchBtn) {
            searchBtn.closest('.nav-action-btn').addEventListener('click', this.toggleSearch);
        }

        // Form validation
        const forms = document.querySelectorAll('form[data-validate]');
        forms.forEach(form => {
            form.addEventListener('submit', this.validateForm);
        });
    }

    // Add to cart
    async addToCart(e) {
        const button = e.target.closest('.btn-cart');
        const productId = button.dataset.productId;
        const quantity = button.dataset.quantity || 1;

        try {
            const response = await fetch('/ajax/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product_id=${productId}&quantity=${quantity}`
            });

            const data = await response.json();

            if (data.success) {
                this.updateCartCount(data.cart_count);
                this.showNotification('Produk berhasil ditambahkan ke keranjang!', 'success');
            } else {
                this.showNotification(data.message || 'Terjadi kesalahan', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.showNotification('Terjadi kesalahan saat menambahkan ke keranjang', 'error');
        }
    }

    // Toggle wishlist
    async toggleWishlist(e) {
        const button = e.target.closest('.btn-wishlist');
        const productId = button.dataset.productId;
        const heartIcon = button.querySelector('i');

        try {
            const response = await fetch('/ajax/wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=toggle&product_id=${productId}`
            });

            const data = await response.json();

            if (data.success) {
                if (data.action === 'added') {
                    heartIcon.classList.replace('far', 'fas');
                    heartIcon.style.color = '#e74c3c';
                    this.showNotification('Produk ditambahkan ke wishlist!', 'success');
                } else {
                    heartIcon.classList.replace('fas', 'far');
                    heartIcon.style.color = '';
                    this.showNotification('Produk dihapus dari wishlist!', 'info');
                }
            }
        } catch (error) {
            console.error('Error:', error);
        }
    }

    // Update cart count in header
    updateCartCount(count = null) {
        const cartCountElement = document.querySelector('.cart-count');
        if (cartCountElement) {
            if (count !== null) {
                cartCountElement.textContent = count;
            } else {
                // Fetch current cart count from server
                fetch('/ajax/cart.php?action=get_count')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            cartCountElement.textContent = data.cart_count;
                        }
                    });
            }
        }
    }

    // Toggle search bar
    toggleSearch() {
        const searchBar = document.getElementById('searchBar');
        if (searchBar) {
            searchBar.style.display = searchBar.style.display === 'none' ? 'block' : 'none';
        }
    }

    // Setup mobile menu
    setupMobileMenu() {
        const menuToggle = document.querySelector('.menu-toggle');
        const navLinks = document.querySelector('.nav-links');

        if (menuToggle && navLinks) {
            menuToggle.addEventListener('click', () => {
                navLinks.classList.toggle('active');
            });
        }
    }

    // Show notification
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas fa-${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
        `;

        // Add styles
        notification.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            background: ${this.getNotificationColor(type)};
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            max-width: 300px;
            animation: slideInRight 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    getNotificationIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-circle',
            warning: 'exclamation-triangle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    }

    getNotificationColor(type) {
        const colors = {
            success: '#28a745',
            error: '#dc3545',
            warning: '#ffc107',
            info: '#17a2b8'
        };
        return colors[type] || '#17a2b8';
    }

    // Form validation
    validateForm(e) {
        const form = e.target;
        const inputs = form.querySelectorAll('[required]');
        let isValid = true;

        inputs.forEach(input => {
            if (!input.value.trim()) {
                isValid = false;
                input.style.borderColor = '#dc3545';
            } else {
                input.style.borderColor = '';
            }
        });

        if (!isValid) {
            e.preventDefault();
            this.showNotification('Harap lengkapi semua field yang wajib diisi!', 'error');
        }
    }

    // Image lazy loading
    setupLazyLoading() {
        const images = document.querySelectorAll('img[data-src]');
        
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.removeAttribute('data-src');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    }

    // Product quantity controls
    setupQuantityControls() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.quantity-btn')) {
                const button = e.target.closest('.quantity-btn');
                const input = button.parentElement.querySelector('.quantity-input');
                const max = parseInt(input.max) || 999;
                const min = parseInt(input.min) || 1;
                let value = parseInt(input.value) || 1;

                if (button.classList.contains('increase')) {
                    value = value < max ? value + 1 : max;
                } else if (button.classList.contains('decrease')) {
                    value = value > min ? value - 1 : min;
                }

                input.value = value;
            }
        });
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.luxuryLiving = new LuxuryLiving();
});

// Utility functions
const Utils = {
    // Format currency
    formatCurrency: (amount) => {
        return 'Rp ' + parseInt(amount).toLocaleString('id-ID');
    },

    // Debounce function
    debounce: (func, wait) => {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    // Get URL parameters
    getUrlParams: () => {
        const params = new URLSearchParams(window.location.search);
        const result = {};
        for (const [key, value] of params) {
            result[key] = value;
        }
        return result;
    },

    // Set URL parameter
    setUrlParam: (key, value) => {
        const url = new URL(window.location);
        url.searchParams.set(key, value);
        window.history.pushState({}, '', url);
    }
};