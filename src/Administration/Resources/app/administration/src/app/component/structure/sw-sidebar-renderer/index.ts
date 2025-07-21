/**
 * @sw-package framework
 */

import { computed, ref, onMounted } from 'vue';
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
        const maxWidth = 0.9 * window.innerWidth;

        let animationFrameId: number | null = null;
        let sidebarElement: HTMLElement | null = null;
        let pendingWidth: number | null = null;

        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const isOverlayMode = computed(() => {
            return sidebarWidth.value > minWidth
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').closeSidebar(locationId);
        };

        const collapseSidebar = () => {
            sidebarWidth.value = minWidth;
        };

        const applyPendingWidth = () => {
            if (pendingWidth !== null) {
                sidebarWidth.value = pendingWidth;
                pendingWidth = null;
            }
            animationFrameId = null;
        };

        const handleResize = (event: MouseEvent) => {
            if (!isResizing.value) return;
            if (!sidebarElement) return;

            const rect = sidebarElement.getBoundingClientRect();
            const newWidth = rect.right - event.clientX;

            pendingWidth = Math.max(minWidth, Math.min(maxWidth, newWidth));

            if (animationFrameId === null) {
                animationFrameId = requestAnimationFrame(applyPendingWidth);
            }
        };

        const stopResize = () => {
            isResizing.value = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleResize, true);
            document.removeEventListener('mouseup', stopResize, true);

            sidebarElement = null;
            if (animationFrameId !== null) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
            pendingWidth = null;

            localStorage.setItem('sw-sidebar-width', sidebarWidth.value.toString());
        };

        const startResize = (event: MouseEvent) => {
            isResizing.value = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            sidebarElement = document.querySelector('.sw-sidebar-renderer.is-active') as HTMLElement;

            document.addEventListener('mousemove', handleResize, { passive: true, capture: true });
            document.addEventListener('mouseup', stopResize, { capture: true });
            event.preventDefault();
        };

        onMounted(() => {
            const savedWidth = localStorage.getItem('sw-sidebar-width');
            if (savedWidth) {
                sidebarWidth.value = Math.max(minWidth, Math.min(maxWidth, parseInt(savedWidth, 10)));
            }
        });

        return {
            activeSidebar,
            sidebars,
            sidebarWidth,
            isResizing,
            isOverlayMode,
            closeSidebar,
            startResize,
            collapseSidebar,
        };
    },
});
