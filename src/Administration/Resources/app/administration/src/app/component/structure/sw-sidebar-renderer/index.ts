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
        const overlayThreshold = 750;

        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const isOverlayMode = computed(() => {
            return sidebarWidth.value > overlayThreshold;
        });

        const overlayWidth = computed(() => {
            if (isOverlayMode.value && typeof window !== 'undefined') {
                const widthAboveThreshold = sidebarWidth.value - overlayThreshold;
                const maxWidthAboveThreshold = maxWidth - overlayThreshold;
                const percentage = 0.3 + (widthAboveThreshold / maxWidthAboveThreshold) * 0.6;
                return Math.floor(window.innerWidth * Math.max(0.3, Math.min(0.9, percentage)));
            }
            return sidebarWidth.value;
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').closeSidebar(locationId);
        };

        const collapseSidebar = () => {
            sidebarWidth.value = minWidth;
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
            
            let newWidth;
            if (isOverlayMode.value) {
                const viewportWidth = window.innerWidth;
                const mouseXPercent = (viewportWidth - event.clientX) / viewportWidth;
                const percentageWidth = Math.max(0.3, Math.min(0.9, mouseXPercent));
                newWidth = overlayThreshold + ((percentageWidth - 0.3) / 0.6) * (maxWidth - overlayThreshold);
            } else {
                const rect = document.querySelector('.sw-sidebar-renderer')?.getBoundingClientRect();
                if (!rect) return;
                newWidth = rect.right - event.clientX;
            }
            
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

            const handleWindowResize = () => {
                if (isOverlayMode.value) {
                    sidebarWidth.value = sidebarWidth.value;
                }
            };

            const handleKeyDown = (event: KeyboardEvent) => {
                if (event.key === 'Escape' && isOverlayMode.value && activeSidebar.value) {
                    closeSidebar(activeSidebar.value.locationId);
                }
            };
            
            window.addEventListener('resize', handleWindowResize);
            document.addEventListener('keydown', handleKeyDown);
            
            (window as any).__sidebarResizeCleanup = () => {
                window.removeEventListener('resize', handleWindowResize);
                document.removeEventListener('keydown', handleKeyDown);
            };
        });

        onUnmounted(() => {
            document.removeEventListener('mousemove', handleResize);
            document.removeEventListener('mouseup', stopResize);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            
            if ((window as any).__sidebarResizeCleanup) {
                (window as any).__sidebarResizeCleanup();
                delete (window as any).__sidebarResizeCleanup;
            }
        });

        watch(sidebarWidth, (newWidth) => {
            localStorage.setItem('sw-sidebar-width', newWidth.toString());
        });

        return {
            activeSidebar,
            sidebars,
            sidebarWidth,
            isResizing,
            isOverlayMode,
            overlayWidth,
            closeSidebar,
            startResize,
            collapseSidebar,
        };
    },
});
