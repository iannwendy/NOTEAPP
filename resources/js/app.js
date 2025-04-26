import './bootstrap';

// Apply user preferences
document.addEventListener('DOMContentLoaded', function() {
    // Handle theme toggle in the preferences form
    const themeSelect = document.getElementById('theme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function() {
            if (this.value === 'dark') {
                document.body.classList.add('dark-theme');
            } else {
                document.body.classList.remove('dark-theme');
            }
        });
    }

    // Handle font size change in the preferences form
    const fontSizeSelect = document.getElementById('font_size');
    if (fontSizeSelect) {
        fontSizeSelect.addEventListener('change', function() {
            document.body.classList.remove('small-font', 'medium-font', 'large-font');
            document.body.classList.add(this.value + '-font');
        });
    }
});
