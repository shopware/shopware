/**
 * @sw-package framework
 */

import { computed, nextTick, ref, shallowRef, onMounted, onUnmounted, onUpdated, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { createFocusTrap } from 'focus-trap';
import type { FocusTrap } from 'focus-trap';
import template from './sw-sidebar-renderer.html.twig';
import './sw-sidebar-renderer.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    setup() {
        const MAIN_CONTENT_MIN_SIZE = 1300;
        const DEFAULT_SIDEBAR_WIDTH = 512;
        const MIN_SIDEBAR_WIDTH = 400;
        const SIDEBAR_MARGIN = 8;

        const sidebarSetWidth = ref(DEFAULT_SIDEBAR_WIDTH);
        const isResizing = ref(false);
        const windowWidth = ref(window.innerWidth);

        const closingSidebar = computed(() => Shopware.Store.get('sidebar').closingSidebar);

        const switchedWhileOpen = computed(() => Shopware.Store.get('sidebar').switchedWhileOpen);

        const activeSidebar = computed(() => {
            return Shopware.Store.get('sidebar').getActiveSidebar;
        });

        const sidebars = computed(() => {
            return Shopware.Store.get('sidebar').sidebars;
        });

        const sidebarDisplayOptions = computed(() => {
            const availableWidth = activeSidebar.value?.resizable
                ? windowWidth.value - MAIN_CONTENT_MIN_SIZE
                : DEFAULT_SIDEBAR_WIDTH;

            const currentWidth = Math.max(MIN_SIDEBAR_WIDTH, sidebarSetWidth.value);
            const isOverlayMode = availableWidth < currentWidth;

            return {
                availableWidth: `${Math.max(availableWidth, 0)}px`,
                currentWidth: `${currentWidth}px`,
                panelWidth: isOverlayMode ? `${currentWidth}px` : `${currentWidth - SIDEBAR_MARGIN}px`,
                isOverlayMode,
                isCollapsable: availableWidth > DEFAULT_SIDEBAR_WIDTH,
                isResizing: isResizing.value,
            };
        });

        const closeSidebar = (locationId: string) => {
            Shopware.Store.get('sidebar').requestCloseSidebar(locationId);
        };

        const collapseSidebar = () => {
            sidebarSetWidth.value = DEFAULT_SIDEBAR_WIDTH;
            localStorage.setItem('sw-sidebar-width', DEFAULT_SIDEBAR_WIDTH.toString());
        };

        const sidebarElements = new Map<string, HTMLElement>();
        const focusTrap = shallowRef<FocusTrap | null>(null);

        const setSidebarElement = (locationId: string, element: Element | ComponentPublicInstance | null) => {
            if (element instanceof HTMLElement) {
                sidebarElements.set(locationId, element);
            } else {
                sidebarElements.delete(locationId);
            }
        };

        // In overlay mode the panel behaves like a modal dialog: the backdrop only blocks
        // pointer interaction, so a focus trap has to keep keyboard and screen reader
        // users out of the visually obscured application behind it. Holds the location id
        // of the panel that currently needs the trap, null otherwise.
        const overlayTrapTarget = computed(() => {
            const sidebar = activeSidebar.value;

            if (!sidebar?.resizable || closingSidebar.value === sidebar.locationId) {
                return null;
            }

            // While dragging, the trap would churn on every threshold crossing;
            // it (re-)engages once the resize settles.
            if (isResizing.value || !sidebarDisplayOptions.value.isOverlayMode) {
                return null;
            }

            return sidebar.locationId;
        });

        const isOverlayModal = (locationId: string) => overlayTrapTarget.value === locationId;

        const activateFocusTrap = () => {
            void nextTick(() => {
                const locationId = overlayTrapTarget.value;
                const panelElement = locationId ? sidebarElements.get(locationId) : null;

                if (!locationId || !panelElement || focusTrap.value) {
                    return;
                }

                focusTrap.value = createFocusTrap(panelElement, {
                    escapeDeactivates: true,
                    // The backdrop owns outside clicks and closes the sidebar itself,
                    // the trap only has to let them through.
                    clickOutsideDeactivates: false,
                    allowOutsideClick: true,
                    returnFocusOnDeactivate: true,
                    delayInitialFocus: false,
                    fallbackFocus: panelElement,
                    onDeactivate: () => {
                        focusTrap.value = null;
                        closeSidebar(locationId);
                    },
                });

                focusTrap.value.activate();
            });
        };

        const deactivateFocusTrap = (returnFocus: boolean) => {
            if (!focusTrap.value) {
                return;
            }

            const trap = focusTrap.value;
            focusTrap.value = null;

            // Override the configured onDeactivate: it requests the sidebar close,
            // which is wrong for every deactivation not initiated by the trap itself.
            trap.deactivate({ returnFocus, onDeactivate: () => {} });
        };

        watch(
            overlayTrapTarget,
            (locationId) => {
                // Focus only returns to the previously focused element when the sidebar goes
                // away. When the trap tears down while the panel stays open (overlay mode
                // ended, panel switched), focus must stay where it is.
                const sidebarGoesAway = !activeSidebar.value || closingSidebar.value !== null;
                deactivateFocusTrap(sidebarGoesAway);

                if (locationId) {
                    activateFocusTrap();
                }
            },
            // Immediate, so a remount while an overlay sidebar is already active in the
            // store still engages the trap.
            { immediate: true },
        );

        const handleSidebarResize = (event: MouseEvent) => {
            if (!isResizing.value) return;

            sidebarSetWidth.value = Math.max(MIN_SIDEBAR_WIDTH, windowWidth.value - event.clientX);
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
            if (activeSidebar.value && !activeSidebar.value?.resizable && sidebarSetWidth.value !== DEFAULT_SIDEBAR_WIDTH) {
                sidebarSetWidth.value = DEFAULT_SIDEBAR_WIDTH;
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
            deactivateFocusTrap(false);
        });

        return {
            activeSidebar,
            sidebars,
            sidebarDisplayOptions,
            closingSidebar,
            switchedWhileOpen,
            closeSidebar,
            startSidebarResize,
            collapseSidebar,
            focusTrap,
            isOverlayModal,
            setSidebarElement,
        };
    },
});
