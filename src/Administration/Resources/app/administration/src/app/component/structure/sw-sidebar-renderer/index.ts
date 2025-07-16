/**
 * @sw-package framework
 */

import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
import template from './sw-sidebar-renderer.html.twig';
import './sw-sidebar-renderer.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    setup() {
        const sidebarWidth = ref(480);
        const isResizing = ref(false);
        const minWidth = 480;
        const maxWidth = 800;

        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').closeSidebar(locationId);
        };

        const startResize = (event: MouseEvent) => {
            isResizing.value = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', handleResize);
            document.addEventListener('mouseup', stopResize);
            event.preventDefault();
        };

        const handleResize = (event: MouseEvent) => {
            if (!isResizing.value) return;
            
            const rect = document.querySelector('.sw-sidebar-renderer')?.getBoundingClientRect();
            if (!rect) return;
            
            const newWidth = rect.right - event.clientX;
            sidebarWidth.value = Math.max(minWidth, Math.min(maxWidth, newWidth));
        };

        const stopResize = () => {
            isResizing.value = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
        };

        onMounted(() => {
            const savedWidth = localStorage.getItem('sw-sidebar-width');
            if (savedWidth) {
                sidebarWidth.value = Math.max(minWidth, Math.min(maxWidth, parseInt(savedWidth, 10)));
            }
        });

        onUnmounted(() => {
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        });

        watch(sidebarWidth, (newWidth) => {
            localStorage.setItem('sw-sidebar-width', newWidth.toString());
        });

        return {
            activeSidebar,
            sidebars,
            sidebarWidth,
            isResizing,
            closeSidebar,
            startResize,
        };
    },
});
