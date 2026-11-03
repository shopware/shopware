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
    name: 'sw-meteor-navigation',
    load: () => import('./src/sw-meteor-navigation'),
    public: {
        props: [
            'fromLink',
        ],
        blocks: [
            'sw_meteor_navigation',
            'sw_meteor_navigation_link',
            'sw_meteor_navigation_link_icon',
            'sw_meteor_navigation_link_label',
        ],
        computed: [
            'hasParentRoute',
            'parentRoute',
        ],
    },
});
