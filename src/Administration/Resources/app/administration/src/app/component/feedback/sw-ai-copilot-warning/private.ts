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
    name: 'sw-ai-copilot-warning',
    load: () => import('./src/sw-ai-copilot-warning'),
    public: {
        props: [
            'text',
        ],
        blocks: [
            'sw_ai_copilot_warning',
        ],
        computed: [
            'warningText',
        ],
    },
});
