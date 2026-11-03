import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-config',
    load: () => import('./src/sw-extension-config'),
    public: {
        props: [
            'namespace',
        ],
        blocks: [
            'sw_extension_config',
        ],
        computed: [
            'domain',
            'myExtensions',
            'defaultThemeAsset',
            'image',
            'extensionLabel',
        ],
        methods: [
            'createdComponent',
            'refreshExtension',
            'onSave',
        ],
    },
});
