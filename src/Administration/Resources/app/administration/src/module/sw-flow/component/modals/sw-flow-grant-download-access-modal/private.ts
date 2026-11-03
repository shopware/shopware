import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-flow-grant-download-access-modal',
    load: () => import('./src/sw-flow-grant-download-access-modal'),
    public: {
        props: [
            'sequence',
            'action',
        ],
        emits: [
            'process-finish',
            'modal-close',
        ],
        blocks: [
            'sw_flow_grant_download_access_modal',
        ],
        computed: [
            'valueOptions',
        ],
        methods: [
            'createdComponent',
            'getConfig',
            'fieldError',
            'onSave',
            'onClose',
        ],
    },
});
