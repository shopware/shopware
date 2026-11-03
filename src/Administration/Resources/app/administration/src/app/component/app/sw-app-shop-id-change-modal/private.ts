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
    name: 'sw-app-shop-id-change-modal',
    load: () => import('./src/sw-app-shop-id-change-modal'),
    public: {
        props: [
            'shopIdCheck',
        ],
        emits: [
            'modal-close',
        ],
        methods: [
            'fetchStrategies',
            'closeModal',
            'setSelectedStrategy',
            'isSelected',
            'getStrategyLabel',
            'getStrategyDescription',
            'getActiveStyle',
            'confirm',
            '_reloadPage',
            'getHumanReadableFingerprintName',
            'getFingerprintDescription',
        ],
    },
});
