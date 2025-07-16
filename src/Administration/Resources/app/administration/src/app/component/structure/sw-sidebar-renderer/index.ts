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
        
        let animationFrameId: number | null = null;
        let sidebarElement: HTMLElement | null = null;
        let pendingWidth: number | null = null;
        let saveTimeout: ReturnType<typeof setTimeout> | null = null;

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
            
            sidebarElement = document.querySelector('.sw-sidebar-renderer.is-active') as HTMLElement;
            
            document.addEventListener('mousemove', handleResize, { passive: true, capture: true });
            document.addEventListener('mouseup', stopResize, { capture: true });
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
                if (!sidebarElement) return;
                const rect = sidebarElement.getBoundingClientRect();
                newWidth = rect.right - event.clientX;
            }
            
            pendingWidth = Math.max(minWidth, Math.min(maxWidth, newWidth));
            
            if (animationFrameId === null) {
                animationFrameId = requestAnimationFrame(applyPendingWidth);
            }
        };

        const applyPendingWidth = () => {
            if (pendingWidth !== null) {
                sidebarWidth.value = pendingWidth;
                pendingWidth = null;
            }
            animationFrameId = null;
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
            
            if (saveTimeout !== null) {
                clearTimeout(saveTimeout);
                saveTimeout = null;
            }
            localStorage.setItem('sw-sidebar-width', sidebarWidth.value.toString());
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
                    collapseSidebar();
                }
            };
            
            window.addEventListener('resize', handleWindowResize, { passive: true });
            document.addEventListener('keydown', handleKeyDown, { passive: true });
            
            (window as any).__sidebarResizeCleanup = () => {
                window.removeEventListener('resize', handleWindowResize);
                document.removeEventListener('keydown', handleKeyDown);
            };
        });

        onUnmounted(() => {
            document.removeEventListener('mousemove', handleResize, true);
            document.removeEventListener('mouseup', stopResize, true);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            
            if (animationFrameId !== null) {
                cancelAnimationFrame(animationFrameId);
                animationFrameId = null;
            }
            if (saveTimeout !== null) {
                clearTimeout(saveTimeout);
                saveTimeout = null;
            }
            sidebarElement = null;
            pendingWidth = null;
            
            if ((window as any).__sidebarResizeCleanup) {
                (window as any).__sidebarResizeCleanup();
                delete (window as any).__sidebarResizeCleanup;
            }
        });

        const debouncedSave = (newWidth: number) => {
            if (saveTimeout !== null) {
                clearTimeout(saveTimeout);
            }
            saveTimeout = setTimeout(() => {
                localStorage.setItem('sw-sidebar-width', newWidth.toString());
                saveTimeout = null;
            }, 100);
        };

        watch(sidebarWidth, (newWidth) => {
            debouncedSave(newWidth);
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
