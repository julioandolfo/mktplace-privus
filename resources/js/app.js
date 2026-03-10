import './bootstrap';

// Initialize dark mode from localStorage before Alpine starts
// (Alpine is automatically provided by Livewire — do NOT import it manually)
if (localStorage.getItem('theme') !== 'light') {
    document.documentElement.classList.add('dark');
} else {
    document.documentElement.classList.remove('dark');
}
