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
    name: 'sw-system-config',
    load: () => import('./src/sw-system-config'),
    public: {
        props: [
            'domain',
            'salesChannelId',
            'salesChannelSwitchable',
            'inherit',
        ],
        emits: [
            'loading-changed',
            'config-changed',
        ],
        slots: [
            'title',
            'beforeElements',
            'card-element',
            'card-element-last',
            'afterElements',
        ],
        blocks: [
            'sw_system_config',
            'sw_system_config_global_compile_notice',
            'sw_system_config_content_tabs',
            'sw_system_config_content_tab',
            'sw_system_config_content_card',
            'sw_system_config_content_compile_notice',
            'sw_system_config_content_card_group',
            'sw_system_config_content_card_field',
        ],
        computed: [
            'isNotDefaultSalesChannel',
            'typesWithMapInheritanceSupport',
            'showGlobalSection',
            'showTabs',
            'tabItems',
            'defaultTabItem',
        ],
        methods: [
            'getFieldError',
            'createdComponent',
            'readConfig',
            'getSchemaForDomain',
            'assignConfigIds',
            'readAll',
            'loadCurrentSalesChannelConfig',
            'saveAll',
            'getChangedConfigData',
            'hasConfigChanges',
            'hasCacheRelevantChanges',
            'getCacheRelevantFieldNames',
            'createErrorNotification',
            'onSalesChannelChanged',
            'hasMapInheritanceSupport',
            'getElementBind',
            'getInheritWrapperBind',
            'getInheritedValue',
            'emitConfig',
            'kebabCase',
            'isMeteorComponent',
            'getMeteorElementBind',
            'getMeteorElementEventsHandler',
            'getTabName',
            'schemaToConfig',
            'getTabIndexByElementOverlap',
            'configToSchema',
        ],
    },
});
