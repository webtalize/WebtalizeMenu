(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('.wtm-filter-btn');
        const menuItems = document.querySelectorAll('.wtm-menu-item');
        const menuContainer = document.querySelector('.wtm-menu-container');
        
        if (!filterButtons.length || !menuItems.length) {
            return;
        }
        
        filterButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const filterValue = this.getAttribute('data-filter');
                
                // Update active state
                filterButtons.forEach(function(btn) {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Filter menu items
                let visibleCount = 0;
                menuItems.forEach(function(item) {
                    const dietaryData = item.getAttribute('data-dietary') || '';
                    const dietaryLabels = dietaryData ? dietaryData.split(' ') : [];
                    
                    if (filterValue === 'all') {
                        // Show all items
                        item.style.display = '';
                        visibleCount++;
                    } else if (dietaryLabels.includes(filterValue)) {
                        // Show items with this label
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        // Hide items without this label
                        item.style.display = 'none';
                    }
                });
                
                // Hide empty categories
                const categories = document.querySelectorAll('.wtm-category');
                categories.forEach(function(category) {
                    const items = category.querySelectorAll('.wtm-menu-item');
                    let hasVisibleItems = false;
                    
                    items.forEach(function(item) {
                        if (item.style.display !== 'none' && item.offsetParent !== null) {
                            hasVisibleItems = true;
                        }
                    });
                    
                    if (!hasVisibleItems && filterValue !== 'all') {
                        category.style.display = 'none';
                    } else {
                        category.style.display = '';
                    }
                });
                
                // Show message if no items visible
                let noItemsMessage = document.querySelector('.wtm-no-filtered-items');
                if (visibleCount === 0 && filterValue !== 'all') {
                    if (!noItemsMessage) {
                        noItemsMessage = document.createElement('p');
                        noItemsMessage.className = 'wtm-no-filtered-items';
                        noItemsMessage.textContent = 'No items match the selected filter.';
                        if (menuContainer) {
                            menuContainer.insertBefore(noItemsMessage, menuContainer.firstChild);
                        }
                    }
                } else {
                    if (noItemsMessage) {
                        noItemsMessage.remove();
                    }
                }
            });
        });
    });
})();

