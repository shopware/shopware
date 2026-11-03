import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-sales-channel-detail-agentic-file',
    load: () => import('./src/sw-sales-channel-detail-agentic-file'),
    public: {
        props: [
            'salesChannel',
        ],
        computed: [
            'salesChannelFileRepository',
            'routeFileName',
            'listRoute',
            'templateColumns',
            'contentSourceTemplates',
            'templateOverrides',
            'description',
            'previewContent',
            'supportsCustomNotes',
            'contentSourcesToggleIcon',
            'contentSourcesToggleLabel',
            'selectedTemplateDefaultContent',
            'canResetTemplateOverride',
            'publicPreviewUrl',
        ],
        methods: [
            'loadFile',
            'loadPreview',
            'onToggleEnabled',
            'isEnabled',
            'hasTemplateOverride',
            'hasUnsavedTemplateOverrides',
            'openTemplateOverrideModal',
            'closeTemplateOverrideModal',
            'applyTemplateOverride',
            'resetTemplateOverride',
            'removeTemplateOverride',
            'getTemplateOverrideContent',
            'getTemplateDefaultContent',
            'getUserProvidedContent',
            'writeCustomNotesToSalesChannelFile',
            'ensureSalesChannelFileConfiguration',
            'findSalesChannelFileConfiguration',
            'writeConfigurationToSalesChannel',
            'ensureSalesChannelFileCollection',
            'getDisplayFileName',
            'getPublicPath',
            'getToggleLabel',
            'getDescription',
            'getDescriptionSnippetKey',
            'formatSnippetPathSegment',
            'getEnabledVariant',
            'getEnabledLabel',
            'getTemplateRoleVariant',
            'getTemplateRoleLabel',
            'getSalesChannelDomainUrl',
            'isStorefrontSalesChannel',
            'buildPublicUrl',
        ],
    },
});
