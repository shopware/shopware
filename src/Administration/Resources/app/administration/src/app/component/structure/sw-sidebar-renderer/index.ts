/**
 * @sw-package framework
 */

import { computed, nextTick, ref, shallowRef, onMounted, onUnmounted, watch } from 'vue';
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
            const availableWidth = windowWidth.value - MAIN_CONTENT_MIN_SIZE;

            // Non-resizable sidebars always keep the default width
            const currentWidth = activeSidebar.value?.resizable
                ? Math.max(MIN_SIDEBAR_WIDTH, sidebarSetWidth.value)
                : DEFAULT_SIDEBAR_WIDTH;
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

        // Lets a switched-in sidebar start its width transition from the previous sidebar's width
        const previousSidebarWidth = ref<string | null>(null);
        watch(activeSidebar, (next, previous) => {
            if (!next || !previous || next.locationId === previous.locationId) {
                return;
            }

            previousSidebarWidth.value = previous.resizable
                ? `${Math.max(MIN_SIDEBAR_WIDTH, sidebarSetWidth.value)}px`
                : `${DEFAULT_SIDEBAR_WIDTH}px`;
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
        // Location id of the panel the current trap is bound to.
        let trappedLocationId: string | null = null;

        const setSidebarElement = (locationId: string, element: Element | ComponentPublicInstance | null) => {
            if (element instanceof HTMLElement) {
                sidebarElements.set(locationId, element);
            } else {
                sidebarElements.delete(locationId);
            }
        };

        // Overlay mode is modal, but the backdrop only blocks pointers, not keyboard access
        const overlayTrapTarget = computed(() => {
            const sidebar = activeSidebar.value;

            if (!sidebar || closingSidebar.value === sidebar.locationId) {
                return null;
            }

            if (!sidebarDisplayOptions.value.isOverlayMode) {
                return null;
            }

            return sidebar.locationId;
        });

        const isOverlayModal = (locationId: string) => overlayTrapTarget.value === locationId;

        const activateFocusTrap = () => {
            // Deferred a tick: the template ref is only populated by the post-render queue
            void nextTick(() => {
                const locationId = overlayTrapTarget.value;
                const panelElement = locationId ? sidebarElements.get(locationId) : null;

                if (!locationId || !panelElement || focusTrap.value) {
                    return;
                }

                trappedLocationId = locationId;
                focusTrap.value = createFocusTrap(panelElement, {
                    escapeDeactivates: true,
                    // The backdrop closes the sidebar itself, the trap only lets clicks through
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
            trappedLocationId = null;

            // Override onDeactivate: its sidebar close is wrong unless the trap initiated it
            trap.deactivate({ returnFocus, onDeactivate: () => {} });
        };

        watch(
            [
                overlayTrapTarget,
                isResizing,
            ],
            ([
                locationId,
                resizing,
            ]) => {
                // Only paused while dragging: deactivating would lose the return focus target
                if (resizing) {
                    focusTrap.value?.pause();
                    return;
                }

                if (locationId) {
                    if (focusTrap.value && trappedLocationId === locationId) {
                        focusTrap.value.unpause();
                        return;
                    }

                    // A trap bound to another panel tears down without moving focus
                    deactivateFocusTrap(false);
                    activateFocusTrap();
                    return;
                }

                // Focus only returns when the sidebar goes away, not when overlay mode ends
                const sidebarGoesAway = !activeSidebar.value || closingSidebar.value !== null;
                deactivateFocusTrap(sidebarGoesAway);
            },
            // Immediate, so a remount with an already active overlay sidebar still traps
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

        onMounted(() => {
            const savedWidth = localStorage.getItem('sw-sidebar-width');
            if (savedWidth) {
                sidebarSetWidth.value = Math.max(parseInt(savedWidth, 10), MIN_SIDEBAR_WIDTH);
            }

            window.addEventListener('resize', handleWindowResize);
        });

        onUnmounted(() => {
            window.removeEventListener('resize', handleWindowResize);
            // Unmounting with an active trap would drop the focus on body
            deactivateFocusTrap(true);
        });

        return {
            activeSidebar,
            sidebars,
            sidebarDisplayOptions,
            closingSidebar,
            switchedWhileOpen,
            previousSidebarWidth,
            closeSidebar,
            startSidebarResize,
            collapseSidebar,
            isOverlayModal,
            setSidebarElement,
        };
    },
});
