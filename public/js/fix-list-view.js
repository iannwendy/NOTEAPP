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
                } else if (currentView === 'grid') {
                    setTimeout(fixGridViewInDarkMode, 50);
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
                // Get note color from data attribute or from inline style
                const noteColor = card.getAttribute('data-note-color') || extractColorFromStyle(card.getAttribute('style'));
                
                if (noteColor && noteColor !== '#ffffff' && noteColor !== '#fff') {
                    // If the note has a custom color, ensure it's applied and adjust text color
                    card.style.setProperty('background-color', noteColor, 'important');
                    
                    // Determine if the background is dark
                    const isDarkBg = isDarkColor(noteColor);
                    card.style.setProperty('color', isDarkBg ? '#ffffff' : '#000000', 'important');
                    
                    // Also apply the color to card-title and card-text elements
                    const textColor = isDarkBg ? '#ffffff' : '#000000';
                    applyColorToCardContents(card, textColor);
                } else {
                    // For default notes, use dark theme styling
                    card.style.setProperty('background-color', '#343a40', 'important');
                    card.style.setProperty('color', '#f8f9fa', 'important');
                    
                    // Also apply the color to card-title and card-text elements
                    applyColorToCardContents(card, '#f8f9fa');
                }
                
                // Apply dark theme border
                card.style.setProperty('border-color', 'rgba(255, 255, 255, 0.2)', 'important');
            } else {
                // Reset to default in light theme
                const noteColor = card.getAttribute('data-note-color') || extractColorFromStyle(card.getAttribute('style')) || '#ffffff';
                card.style.setProperty('background-color', noteColor, 'important');
                
                // Set text color based on background color
                let textColor;
                if (noteColor === '#ffffff' || noteColor === '#fff' || isLightColor(noteColor)) {
                    textColor = '#000000';
                } else {
                    textColor = '#ffffff';
                }
                
                card.style.setProperty('color', textColor, 'important');
                
                // Also apply the color to card-title and card-text elements
                applyColorToCardContents(card, textColor);
                
                card.style.setProperty('border-color', 'rgba(0, 0, 0, 0.125)', 'important');
            }
        });
    }
    
    // Function to ensure all card content elements have the correct color
    function applyColorToCardContents(card, textColor) {
        // Apply text color to title
        const cardTitle = card.querySelector('.card-title');
        if (cardTitle) {
            cardTitle.style.setProperty('color', textColor, 'important');
        }
        
        // Apply text color to content
        const cardText = card.querySelector('.card-text');
        if (cardText) {
            cardText.style.setProperty('color', textColor, 'important');
        }
        
        // Apply to any other text elements that might be in the card
        const cardBody = card.querySelector('.card-body');
        if (cardBody) {
            cardBody.style.setProperty('color', textColor, 'important');
            
            // Apply color to all text elements in the card body
            const paragraphs = cardBody.querySelectorAll('p, h1, h2, h3, h4, h5, h6, div, span');
            paragraphs.forEach(p => {
                p.style.setProperty('color', textColor, 'important');
            });
        }
    }
    
    // Function to extract background color from style attribute
    function extractColorFromStyle(styleString) {
        if (!styleString) return null;
        
        // Extract background-color from style attribute
        const match = styleString.match(/background-color:\s*([^;]+)/i);
        if (match && match[1]) {
            return match[1].trim();
        }
        return null;
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
    
    // Run grid view fix when switching to grid view
    gridViewBtn.addEventListener('click', function() {
        setTimeout(fixGridViewInDarkMode, 50);
    });
    
    // Observe for theme class changes on body (use the existing observer)
    observer.disconnect();
    observer.observe(document.body, { attributes: true });
}); 