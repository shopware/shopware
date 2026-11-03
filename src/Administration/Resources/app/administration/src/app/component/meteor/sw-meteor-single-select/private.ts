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
    name: 'sw-meteor-single-select',
    load: () => import('./src/sw-meteor-single-select'),
    public: {
        props: [
            'options',
            'value',
            'label',
            'isLoading',
            'highlightSearchTerm',
            'placeholder',
            'labelProperty',
            'valueProperty',
        ],
        emits: [
            'paginate',
            'update:value',
            'search',
        ],
        slots: [
            'before-item-list',
            'result-item',
            'result-label-property',
            'after-item-list',
        ],
        blocks: [
            'sw_meteor_single_select',
            'sw_meteor_single_select_preview',
            'sw_meteor_single_select_result_list',
        ],
        computed: [
            'currentValue',
            'inputClasses',
            'selectionTextClasses',
            'singleSelection',
            'selectedValueLabel',
            'searchable',
        ],
        methods: [
            'isSelected',
            'toggleResultList',
            'openResultList',
            'closeResultList',
            'setValue',
            'onInputSearchTerm',
            'debouncedSearch',
            'search',
            'getKey',
        ],
    },
});
