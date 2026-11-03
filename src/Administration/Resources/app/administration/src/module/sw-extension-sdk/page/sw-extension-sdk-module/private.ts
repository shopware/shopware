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
    name: 'sw-extension-sdk-module',
    load: () => import('./src/sw-extension-sdk-module'),
    public: {
        props: [
            'id',
            'back',
        ],
        blocks: [
            'sw_extension_sdk_module',
            'sw_extension_sdk_module_header',
            'sw_extension_sdk_module_smart_bar_buttons',
            'sw_extension_sdk_module_content',
            'sw_extension_sdk_module_content_iframe_renderer',
            'sw_extension_sdk_module_content_loader',
            'sw_extension_sdk_module_content_error_state',
            'sw_extension_sdk_module_language_switch',
        ],
        computed: [
            'module',
            'isLoading',
            'showSearchBar',
            'showSmartBar',
            'showLanguageSwitch',
            'smartBarButtons',
        ],
        methods: [
            'onChangeLanguage',
        ],
    },
});
