// Force CSS reload by appending timestamp and fix list view styling in dark mode
document.addEventListener('DOMContentLoaded', function() {
    // Find all CSS link elements
    const cssLinks = document.querySelectorAll('link[rel="stylesheet"]');
    
    // Add timestamp parameter to force cache refresh
    cssLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes('custom.css')) {
            const timestamp = new Date().getTime();
            const newHref = href.includes('?') 
                ? `${href}&v=${timestamp}` 
                : `${href}?v=${timestamp}`;
            link.setAttribute('href', newHref);
            console.log('Refreshed CSS cache for:', newHref);
        }
    });
    
    // Direct style fix for list view items in dark mode
    if (document.body.classList.contains('dark-theme')) {
        // Wait a tiny bit for DOM to be fully ready
        setTimeout(() => {
            const listItems = document.querySelectorAll('#list-view .list-group-item.note-card');
            
            listItems.forEach(item => {
                // Get the background color from style attribute
                const style = item.getAttribute('style') || '';
                const bgMatch = style.match(/background-color:\s*(#[0-9a-f]{6}|#[0-9a-f]{3})/i);
                
                if (bgMatch) {
                    const bgColor = bgMatch[1].toLowerCase();
                    const darkColors = ['#212529', '#343a40', '#495057', '#000000', '#111111', '#222222', '#333333'];
                    
                    // If it's a dark color, ensure text is white
                    if (darkColors.includes(bgColor)) {
                        item.style.color = '#ffffff !important';
                    } else {
                        // Otherwise, ensure text is black for contrast
                        item.style.color = '#000000 !important';
                    }
                } else {
                    // If no background color, set to dark theme default
                    item.style.backgroundColor = '#343a40 !important';
                    item.style.color = '#f8f9fa !important';
                    item.style.borderColor = 'rgba(255, 255, 255, 0.2) !important';
                }
            });
            
            console.log('Applied direct style fixes to list items in dark mode');
        }, 100);
    }
}); 