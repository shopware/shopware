import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-cms-el-config-location-renderer',
    load: () => import('./src/sw-cms-el-config-location-renderer'),
    public: {
        props: [
            'elementData',
        ],
        emits: [
            'element-update',
        ],
        blocks: [
            'sw_cms_el_config_location_renderer',
        ],
        computed: [
            'src',
            'configLocation',
            'publishingKey',
        ],
        methods: [
            'createdComponent',
            'onBlur',
            'onInput',
            'emitChanges',
        ],
    },
});
