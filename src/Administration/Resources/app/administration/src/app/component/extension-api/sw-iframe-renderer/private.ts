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
    name: 'sw-iframe-renderer',
    load: () => import('./src/sw-iframe-renderer'),
    public: {
        props: [
            'src',
            'locationId',
            'fullScreen',
        ],
        blocks: [
            'sw_iframe_renderer',
        ],
        computed: [
            'locationIdHashQueryKey',
            'locationIdPathnameQueryKey',
            'locationIdSearchParamsQueryKey',
            'componentName',
            'extension',
            'extensionIsApp',
            'iFrameSrc',
            'iFrameHeight',
            'classes',
        ],
        methods: [
            'onIframeLoad',
            '_reloadPage',
            '_getLocationOrigin',
            'signIframeSrc',
        ],
    },
});
