// UI behavior without inline handlers, to fit the CSP (script-src 'self').
document.addEventListener('submit', function (event) {
    var form = event.target;

    if (!form.matches('form[data-confirm]')) {
        return;
    }

    if (!window.confirm(form.getAttribute('data-confirm'))) {
        event.preventDefault();
    }
});

// Theme (Atlas | PALO IT): swaps the tokens immediately and stores the choice
// in a cookie so the server renders the next page with the right theme.
window.labSetBrand = function (brand) {
    if (brand !== 'atlas' && brand !== 'palo') {
        return;
    }
    document.documentElement.setAttribute('data-brand', brand);
    document.cookie = 'brand=' + brand + '; path=/; max-age=31536000; SameSite=Lax' +
        (location.protocol === 'https:' ? '; Secure' : '');
};
