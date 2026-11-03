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
    name: 'sw-cms-inherit-wrapper',
    load: () => import('./src/sw-cms-inherit-wrapper'),
    public: {
        props: [
            'element',
            'field',
            'fieldPath',
            'label',
        ],
        slots: [
            'default',
        ],
        computed: [
            'cmsElements',
            'baseConfig',
            'childConfig',
            'runtimeConfig',
            'supportsInheritance',
            'isSystemDefaultLanguage',
            'isInherited',
            'fullPath',
            'inheritedFieldConfig',
            'fieldDefaultValue',
        ],
        methods: [
            'onInheritanceRestore',
            'onInheritanceRemove',
        ],
    },
});
