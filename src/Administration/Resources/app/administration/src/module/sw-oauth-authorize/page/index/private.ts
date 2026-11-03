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
    name: 'sw-oauth-authorize-index',
    load: () => import('./src/sw-oauth-authorize-index'),
    public: {
        blocks: [
            'sw_oauth_authorize',
        ],
        computed: [
            'authorizationParams',
            'hasRequiredParams',
            'username',
            'clientName',
            'redirectHost',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'loadInfo',
            'loadShopName',
            'onApprove',
            'onDeny',
            'submitDecision',
            'getErrorMessage',
            'navigateTo',
        ],
    },
});
