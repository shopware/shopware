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
    name: 'sw-sales-channel-detail-agentic-files',
    load: () => import('./src/sw-sales-channel-detail-agentic-files'),
    public: {
        props: [
            'salesChannel',
        ],
        computed: [
            'salesChannelFileRepository',
            'columns',
            'paginatedFiles',
            'total',
        ],
        methods: [
            'loadFiles',
            'onToggleEnabled',
            'isEnabled',
            'ensureSalesChannelFileConfiguration',
            'findSalesChannelFileConfiguration',
            'ensureSalesChannelFileCollection',
            'hasTemplateOverrides',
            'getDisplayFileName',
            'getDetailRoute',
            'getPublicPath',
            'getToggleLabel',
            'getDescription',
            'getDescriptionSnippetKey',
            'formatSnippetPathSegment',
            'getEnabledVariant',
            'getEnabledLabel',
            'onChangePage',
        ],
    },
});
