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
    name: 'sw-select-result-list',
    load: () => import('./src/sw-select-result-list'),
    public: {
        props: [
            'options',
            'emptyMessage',
            'focusEl',
            'isLoading',
            'popoverClasses',
            'popoverResizeWidth',
        ],
        emits: [
            'item-select',
            'active-item-change',
            'outside-click',
            'paginate',
            'item-select-by-keyboard',
        ],
        slots: [
            'before-item-list',
            'result-item',
            'after-item-list',
        ],
        blocks: [
            'sw_select_result_list',
            'sw_select_result_list_empty_icon',
            'sw_select_result_list_empty_text',
        ],
        computed: [
            'emptyMessageText',
            'popoverClass',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyedComponent',
            'setActiveItemIndex',
            'addEventListeners',
            'removeEventListeners',
            'onItemSelect',
            'emitActiveItemIndex',
            'checkOutsideClick',
            'navigate',
            'navigateNext',
            'navigatePrevious',
            'updateScrollPosition',
            'emitClicked',
            'onScroll',
            'getBottomDistance',
        ],
    },
});
