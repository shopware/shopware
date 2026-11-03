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
    name: 'sw-version',
    load: () => import('./src/sw-version'),
    public: {
        blocks: [
            'sw_version',
            'sw_version_info',
            'sw_version_info_text',
        ],
        computed: [
            'version',
        ],
        methods: [
            'getHumanReadableText',
        ],
    },
});
