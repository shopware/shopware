/**
 * @sw-package framework
 */

import { computed, ref, onMounted, onUnmounted, onUpdated } from 'vue';
import template from './sw-sidebar-renderer.html.twig';
import './sw-sidebar-renderer.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    setup() {
        const MAIN_CONTENT_MIN_SIZE = 1400;
        const MIN_SIDEBAR_WIDTH = 480;

        const sidebarSetWidth = ref(480);
        const isResizing = ref(false);
        const windowWidth = ref(window.innerWidth);

        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const sidebarDisplayOptions = computed(() => {
            const availableWidth = activeSidebar.value?.resizable
                ? windowWidth.value - MAIN_CONTENT_MIN_SIZE
                : MIN_SIDEBAR_WIDTH;

            const currentWidth = Math.max(MIN_SIDEBAR_WIDTH, sidebarSetWidth.value);
            return {
                availableWidth: `${Math.max(availableWidth, 0)}px`,
                currentWidth: `${currentWidth}px`,
                isOverlayMode: availableWidth < currentWidth,
                isCollapsable: availableWidth > MIN_SIDEBAR_WIDTH,
                isResizing: isResizing.value,
            };
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').closeSidebar(locationId);
        };

        const collapseSidebar = () => {
            sidebarSetWidth.value = MIN_SIDEBAR_WIDTH;
        };

        const handleSidebarResize = (event: MouseEvent) => {
            if (!isResizing.value) return;

            sidebarSetWidth.value = windowWidth.value - event.clientX;
        };

        const stopSidebarResize = () => {
            isResizing.value = false;
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            document.removeEventListener('mousemove', handleSidebarResize, true);
            document.removeEventListener('mouseup', stopSidebarResize, true);

            localStorage.setItem('sw-sidebar-width', sidebarSetWidth.value.toString());
        };

        const startSidebarResize = (event: MouseEvent) => {
            if (!activeSidebar.value?.resizable) return;

            isResizing.value = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';

            document.addEventListener('mousemove', handleSidebarResize, { passive: true, capture: true });
            document.addEventListener('mouseup', stopSidebarResize, { capture: true });
            event.preventDefault();
        };

        const handleWindowResize = () => {
            windowWidth.value = window.innerWidth;
        };

        onUpdated(() => {
            if (activeSidebar.value && !activeSidebar.value?.resizable && sidebarSetWidth.value !== MIN_SIDEBAR_WIDTH) {
                sidebarSetWidth.value = MIN_SIDEBAR_WIDTH;
            }
        });

        onMounted(() => {
            const savedWidth = localStorage.getItem('sw-sidebar-width');
            if (savedWidth) {
                sidebarSetWidth.value = Math.max(parseInt(savedWidth, 10), MIN_SIDEBAR_WIDTH);
            }

            window.addEventListener('resize', handleWindowResize);
        });

        onUnmounted(() => {
            window.removeEventListener('resize', handleWindowResize);
        });

        return {
            activeSidebar,
            sidebars,
            sidebarDisplayOptions,
            closeSidebar,
            startSidebarResize,
            collapseSidebar,
        };
    },
});
