import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Initialize dark mode from localStorage before Alpine starts
if (localStorage.getItem('theme') !== 'light') {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}

Alpine.start();
