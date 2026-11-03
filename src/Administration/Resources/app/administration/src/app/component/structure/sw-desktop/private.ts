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
    name: 'sw-desktop',
    load: () => import('./src/sw-desktop'),
    public: {
        blocks: [
            'sw_desktop',
            'sw_desktop_sidebar',
            'sw_desktop_content',
            'sw_desktop_content_view',
        ],
        computed: [
            'desktopClasses',
            'currentUser',
            'isStaging',
            'showUsageDataConsentModalDataProvider',
            'showWrongAppUrlModal',
        ],
        methods: [
            'createdComponent',
            'checkRouteSettings',
            'updateShopIdChangeModal',
            'closeModal',
            'onCycleTheme',
            'onUpdateSearchFrequently',
            'getModuleMetadata',
            'getModuleMetadataWithSearchMatcher',
        ],
    },
});
