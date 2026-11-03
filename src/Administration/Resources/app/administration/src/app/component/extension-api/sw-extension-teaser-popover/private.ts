import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package innovation
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-teaser-popover',
    load: () => import('./src/sw-extension-teaser-popover'),
    public: {
        props: [
            'positionIdentifier',
            'component',
        ],
        blocks: [
            'sw_extension_teaser_popover',
        ],
        computed: [
            'popoverComponent',
            'isInsideComponent',
        ],
        methods: [
            'onMouseEnterTrigger',
            'onMouseEnterContent',
            'onMouseLeaveContent',
            'onMouseLeaveTrigger',
        ],
    },
});
