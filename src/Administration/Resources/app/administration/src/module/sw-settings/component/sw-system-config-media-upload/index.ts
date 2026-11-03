import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-system-config-media-upload',
    load: () => import('./src/sw-system-config-media-upload'),
    public: {
        props: [
            'value',
            'disabled',
            'label',
            'name',
        ],
        emits: [
            'update:value',
        ],
        methods: [
            'successfulUpload',
            'setMedia',
            'removeMedia',
        ],
    },
});
