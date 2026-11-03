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
    name: 'sw-condition-value-between-date',
    load: () => import('./src/sw-condition-value-between-date'),
    public: {
        props: [
            'value',
            'dateType',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_condition_value_between_date',
        ],
        computed: [
            'from',
            'to',
        ],
    },
});
