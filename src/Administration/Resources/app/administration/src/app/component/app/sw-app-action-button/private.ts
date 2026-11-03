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
    name: 'sw-app-action-button',
    load: () => import('./src/sw-app-action-button'),
    public: {
        props: [
            'action',
        ],
        emits: [
            'run-app-action',
        ],
        blocks: [
            'sw_connect_action_button',
        ],
        computed: [
            'buttonLabel',
        ],
        methods: [
            'runAction',
        ],
    },
});
