import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-tabs-deprecated',
    load: () => import('./src/sw-tabs-deprecated'),
    public: {
        props: [
            'positionIdentifier',
            'isVertical',
            'small',
            'alignRight',
            'defaultItem',
        ],
        emits: [
            'new-item-active',
        ],
        slots: [
            'default',
            'content',
        ],
        blocks: [
            'sw_tabs',
            'sw_tabs_content',
            'sw_tabs_content_slot',
            'sw_tabs_slider',
            'sw_tabs_arrow_left',
            'sw_tabs_arrow_right',
            'sw_tabs_custom_content',
        ],
        computed: [
            'tabClasses',
            'arrowClassesLeft',
            'arrowClassesRight',
            'sliderLength',
            'activeTabHasErrors',
            'activeTabHasWarnings',
            'sliderClasses',
            'sliderMovement',
            'sliderStyle',
            'tabContentStyle',
            'tabExtensions',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'beforeDestroyComponent',
            'registerOnNewItemActiveHandler',
            'registerNewTabItem',
            'unregisterNewTabItem',
            'onNewItemActiveHandler',
            'onTabBarResize',
            'recalculateSlider',
            'updateActiveItem',
            'scrollTo',
            'checkIfNeedScroll',
            'setActiveItem',
            'scrollToItem',
            'addScrollbarOffset',
        ],
    },
});
