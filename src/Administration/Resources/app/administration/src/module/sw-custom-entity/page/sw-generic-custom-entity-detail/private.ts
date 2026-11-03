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
    name: 'sw-generic-custom-entity-detail',
    load: () => import('./src/sw-generic-custom-entity-detail'),
    public: {
        blocks: [
            'sw_generic_custom_entity_detail',
        ],
        computed: [
            'customEntityDataId',
            'customEntityName',
            'customEntityDataDefinition',
            'customEntityDataRepository',
            'customEntityProperties',
            'adminConfig',
            'entityAccentColor',
            'detailTabs',
            'detailTabItems',
            'activeTabName',
            'mainTabName',
            'titlePropertyName',
        ],
        methods: [
            'createdComponent',
            'initializeCustomEntity',
            'loadData',
            'onSave',
            'saveFinish',
            'onChangeLanguage',
            'getFieldTranslation',
            'getLabel',
            'getPlaceholder',
            'getHelpText',
            'getType',
            'updateCmsPageId',
            'updateCmsSlotOverwrites',
            'updateSeoMetaTitle',
            'updateSeoMetaDescription',
            'updateSeoUrl',
            'updateOgTitle',
            'updateOgDescription',
            'updateOgImageId',
            'onCreateLayout',
        ],
    },
});
