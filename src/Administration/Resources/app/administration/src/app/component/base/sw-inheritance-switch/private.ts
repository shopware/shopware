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
    name: 'sw-inheritance-switch',
    load: () => import('./src/sw-inheritance-switch'),
    public: {
        props: [
            'isInherited',
            'disabled',
        ],
        emits: [
            'inheritance-restore',
            'inheritance-remove',
        ],
        blocks: [
            'sw_inheritance_switch',
            'sw_inheritance_switch_inherit_icon',
            'sw_inheritance_switch_uninherit_icon',
        ],
        computed: [
            'unInheritClasses',
        ],
        methods: [
            'onClickRestoreInheritance',
            'onClickRemoveInheritance',
        ],
    },
});
