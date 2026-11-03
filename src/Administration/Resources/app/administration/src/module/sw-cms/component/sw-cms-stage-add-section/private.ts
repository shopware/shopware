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
    name: 'sw-cms-stage-add-section',
    load: () => import('./src/sw-cms-stage-add-section'),
    public: {
        props: [
            'forceChoose',
            'disabled',
        ],
        emits: [
            'stage-section-add',
        ],
        blocks: [
            'sw_cms_add_section',
            'sw_cms_add_section_selection',
            'sw_cms_add_section_button',
        ],
        computed: [
            'componentClasses',
        ],
        methods: [
            'onAddSection',
            'toggleSelection',
        ],
    },
});
