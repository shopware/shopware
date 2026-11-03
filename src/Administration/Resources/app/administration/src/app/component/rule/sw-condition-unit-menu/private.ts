import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-condition-unit-menu',
    load: () => import('./src/sw-condition-unit-menu'),
    public: {
        props: [
            'type',
            'value',
            'visibleValue',
        ],
        emits: [
            'set-default-unit',
            'change-unit',
        ],
        blocks: [
            'sw_condition_unit_menu',
        ],
        computed: [
            'defaultUnit',
            'unitSnippet',
            'unitOptions',
        ],
        methods: [
            'onUnitChange',
            'getConvertedValue',
            'isSelected',
        ],
    },
});
