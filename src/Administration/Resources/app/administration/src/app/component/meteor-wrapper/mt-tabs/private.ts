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
    name: 'mt-tabs',
    load: () => import('./src/mt-tabs'),
    public: {
        props: [
            'positionIdentifier',
            'defaultItem',
            'useRoutesForExtensions',
            'small',
            'vertical',
            'items',
        ],
        emits: [
            'new-item-active',
        ],
        slots: [
            'content',
        ],
        blocks: [
            'mt_tabs',
        ],
        computed: [
            'meteorAttributes',
            'tabExtensions',
            'activeItemName',
            'extensionTabsUseRoutes',
            'activeTabExtension',
            'hasCustomContent',
            'mergedItems',
        ],
        methods: [
            'onNewItemActive',
        ],
    },
});
