// Fix list view styling in dark mode when switching between views
document.addEventListener('DOMContentLoaded', function() {
    const gridViewBtn = document.getElementById('grid-view-btn');
    const listViewBtn = document.getElementById('list-view-btn');
    
    if (!gridViewBtn || !listViewBtn) return;
    
    // Fix list view items when switching to list view
    listViewBtn.addEventListener('click', function() {
        if (document.body.classList.contains('dark-theme')) {
            setTimeout(fixListViewInDarkMode, 50);
        }
    });
    
    // Check if we start in list view and fix it
    if (localStorage.getItem('notesViewPreference') === 'list') {
        setTimeout(fixListViewInDarkMode, 100);
    }
    
    // Also monitor for theme changes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class' && 
                mutation.target === document.body) {
                const currentView = localStorage.getItem('notesViewPreference') || 'grid';
                if (currentView === 'list') {
                    setTimeout(fixListViewInDarkMode, 50);
                }
            }
        });
    });
    
    observer.observe(document.body, { attributes: true });
    
    // Function to check if the document is in dark mode
    function isDarkMode() {
        return document.body.classList.contains('dark-theme');
    }
    
    // Function to determine if a color is light (should use dark text) or dark (should use light text)
    function isLightColor(hexColor) {
        if (!hexColor || hexColor === 'transparent' || hexColor === 'inherit') return false;
        
        // Remove the hash if it exists
        hexColor = hexColor.replace('#', '');
        
        // Handle shorthand hex (e.g. #fff)
        if (hexColor.length === 3) {
            hexColor = hexColor[0] + hexColor[0] + hexColor[1] + hexColor[1] + hexColor[2] + hexColor[2];
        }
        
        // Convert to RGB
        const r = parseInt(hexColor.substr(0, 2), 16);
        const g = parseInt(hexColor.substr(2, 2), 16);
        const b = parseInt(hexColor.substr(4, 2), 16);
        
        // Calculate brightness (higher values are lighter colors)
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        
        // Return true for light colors (should use dark text)
        return brightness > 160;
    }
    
    // Function to fix list view items in dark mode
    function fixListViewInDarkMode() {
        // Apply styling directly to list view items
        const listItems = document.querySelectorAll('.list-group-item.note-card');
        const isDarkTheme = document.body.classList.contains('dark-theme');
        
        console.log('Fixing list view in theme mode:', isDarkTheme ? 'dark' : 'light', 'items:', listItems.length);
        
        listItems.forEach(item => {
            if (isDarkTheme) {
                // Apply dark theme styles
                const noteColor = item.getAttribute('data-note-color') || null;
                
                if (noteColor && noteColor !== '#ffffff' && noteColor !== '#fff') {
                    // If the note has a custom color, keep it but adjust text color
                    item.style.setProperty('background-color', noteColor, 'important');
                    
                    // Determine if the background is dark
                    const isDarkBg = isDarkColor(noteColor);
                    item.style.setProperty('color', isDarkBg ? '#ffffff' : '#000000', 'important');
                } else {
                    // For default notes, use dark theme styling
                    item.style.setProperty('background-color', '#343a40', 'important');
                    item.style.setProperty('color', '#f8f9fa', 'important');
                }
                
                item.style.setProperty('border-color', 'rgba(255, 255, 255, 0.2)', 'important');
            } else {
                // Reset to default in light theme
                const noteColor = item.getAttribute('data-note-color') || '#ffffff';
                item.style.setProperty('background-color', noteColor, 'important');
                
                // Set text color based on background color
                if (noteColor === '#ffffff' || noteColor === '#fff' || isLightColor(noteColor)) {
                    item.style.setProperty('color', '#000000', 'important');
                } else {
                    item.style.setProperty('color', '#ffffff', 'important');
                }
                
                item.style.setProperty('border-color', 'rgba(0, 0, 0, 0.125)', 'important');
            }
        });
    }
    
    // Function to fix grid view items in dark mode
    function fixGridViewInDarkMode() {
        // Apply styling directly to grid view items
        const gridCards = document.querySelectorAll('#grid-view .card.note-card');
        const isDarkTheme = document.body.classList.contains('dark-theme');
        
        console.log('Fixing grid view in theme mode:', isDarkTheme ? 'dark' : 'light', 'items:', gridCards.length);
        
        gridCards.forEach(card => {
            if (isDarkTheme) {
                // Get the computed background color
                const style = window.getComputedStyle(card);
                const backgroundColor = style.backgroundColor;
                
                // Parse the RGB values
                const rgbMatch = backgroundColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)(?:,\s*[\d.]+)?\)/);
                if (rgbMatch) {
                    const r = parseInt(rgbMatch[1], 10);
                    const g = parseInt(rgbMatch[2], 10);
                    const b = parseInt(rgbMatch[3], 10);
                    
                    // Calculate brightness
                    const brightness = (r * 299 + g * 587 + b * 114) / 1000;
                    
                    // Set text color based on background brightness
                    if (brightness > 160) { // Light background
                        card.style.setProperty('color', '#000000', 'important');
                    } else { // Dark background
                        card.style.setProperty('color', '#f8f9fa', 'important');
                    }
                } else {
                    // Default to light text for dark mode if we can't parse the color
                    card.style.setProperty('color', '#f8f9fa', 'important');
                }
                
                // Apply dark theme border
                card.style.setProperty('border-color', 'rgba(255, 255, 255, 0.2)', 'important');
            }
        });
    }
    
    // Function to determine if a color is dark (should use light text)
    function isDarkColor(hexColor) {
        if (!hexColor) return true;
        
        // Remove the hash if it exists
        hexColor = hexColor.replace('#', '');
        
        // Handle shorthand hex (e.g. #fff)
        if (hexColor.length === 3) {
            hexColor = hexColor[0] + hexColor[0] + hexColor[1] + hexColor[1] + hexColor[2] + hexColor[2];
        }
        
        // Convert to RGB
        const r = parseInt(hexColor.substr(0, 2), 16);
        const g = parseInt(hexColor.substr(2, 2), 16);
        const b = parseInt(hexColor.substr(4, 2), 16);
        
        // Calculate brightness (higher values are lighter colors)
        const brightness = (r * 299 + g * 587 + b * 114) / 1000;
        
        // Return true for dark colors (should use light text)
        return brightness < 160;
    }
    
    // Run the fix when page loads
    fixListViewInDarkMode();
    fixGridViewInDarkMode();
    
    // Fix when the theme changes
    const themeToggle = document.getElementById('theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            // Wait for theme to actually change
            setTimeout(() => {
                fixListViewInDarkMode();
                fixGridViewInDarkMode();
            }, 100);
        });
    }
    
    // Observe for theme class changes on body (use the existing observer)
    observer.disconnect();
    observer.observe(document.body, { attributes: true });
}); 