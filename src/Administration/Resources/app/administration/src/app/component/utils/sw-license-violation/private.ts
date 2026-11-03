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
    name: 'sw-license-violation',
    load: () => import('./src/sw-license-violation'),
    public: {
        blocks: [
            'sw_license_violation',
        ],
        computed: [
            'violations',
            'warnings',
            'visible',
            'pluginCriteria',
            'isLoading',
        ],
        methods: [
            'getPluginViolation',
            'reloadViolations',
            'deactivateTemporary',
            'fetchPlugins',
            'deletePlugin',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'getPluginForViolation',
            'addLoading',
            'finishLoading',
        ],
    },
});
