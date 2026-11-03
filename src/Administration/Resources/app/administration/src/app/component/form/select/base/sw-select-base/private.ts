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
    name: 'sw-select-base',
    load: () => import('./src/sw-select-base'),
    public: {
        props: [
            'isLoading',
            'disabled',
            'showClearableButton',
            'size',
        ],
        emits: [
            'select-expanded',
            'select-collapsed',
            'clear',
        ],
        slots: [
            'sw-select-selection',
            'results-list',
            'label',
            'hint',
        ],
        blocks: [
            'sw_select_base',
        ],
        computed: [
            'swFieldClasses',
            'isClearable',
        ],
        methods: [
            'onMounted',
            'onBeforeUnmount',
            'handleKeydown',
            'toggleExpand',
            'expand',
            'collapse',
            'focusPreviousFormElement',
            'listenToClickOutside',
            'computePath',
            'emitClear',
            'focusParentSelect',
        ],
    },
});
