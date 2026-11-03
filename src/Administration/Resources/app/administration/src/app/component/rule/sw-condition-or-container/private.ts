import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-condition-or-container',
    load: () => import('./src/sw-condition-or-container'),
    public: {
        blocks: [
            'sw_condition_and_container',
            'sw_condition_or_container_children',
            'sw_condition_or_container_actions',
        ],
        methods: [
            'createdComponent',
            'onAddPlaceholder',
            'onAddAndContainer',
            'onDeleteAll',
            'getNoPermissionsTooltip',
        ],
    },
});
