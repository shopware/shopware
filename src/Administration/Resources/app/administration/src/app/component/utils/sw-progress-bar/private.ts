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
    name: 'sw-progress-bar',
    load: () => import('./src/sw-progress-bar'),
    public: {
        props: [
            'value',
            'maxValue',
        ],
        blocks: [
            'sw_progress_bar',
            'sw_progress_bar_total',
            'sw_progress_bar_total_value',
        ],
        computed: [
            'styleWidth',
            'progressClasses',
        ],
    },
});
