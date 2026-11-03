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
    name: 'sw-tabs-item',
    load: () => import('./src/sw-tabs-item'),
    public: {
        props: [
            'route',
            'active',
            'activeTab',
            'name',
            'hasError',
            'hasWarning',
            'disabled',
            'errorTooltip',
            'warningTooltip',
        ],
        emits: [
            'click',
        ],
        slots: [
            'default',
        ],
        blocks: [
            'sw_side_navigation_item',
            'sw_tabs_item_slot',
            'sw_tabs_item_slot_native',
        ],
        computed: [
            'isNative',
            'tabsItemClasses',
        ],
        methods: [
            'createdComponent',
            'beforeUpdateComponent',
            'mountedComponent',
            'updateActiveState',
            'clickEvent',
            'checkIfActive',
            'checkIfRouteMatchesLink',
        ],
    },
});
