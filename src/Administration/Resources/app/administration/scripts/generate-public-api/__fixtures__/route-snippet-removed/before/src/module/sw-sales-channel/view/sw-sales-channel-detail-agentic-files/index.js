/**
 * @sw-package discovery
 */

import template from './sw-sales-channel-detail-agentic-files.html.twig';

const FILE_FAMILY_AGENTIC = 'agentic';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['salesChannelFileApiService'],

    props: {
        salesChannel: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            files: [],
            isLoading: false,
        };
    },

    computed: {
        selectedFileName() {
            return this.$route.params.fileName ?? null;
        },
    },

    created() {
        this.loadFiles();
    },

    methods: {
        async loadFiles() {
            if (!this.salesChannel) {
                return;
            }

            this.isLoading = true;
            this.files = await this.salesChannelFileApiService.list(FILE_FAMILY_AGENTIC, this.salesChannel.id);
            this.isLoading = false;
        },
    },
};
