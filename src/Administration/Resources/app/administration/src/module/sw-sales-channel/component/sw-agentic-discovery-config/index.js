/**
 * @sw-package fundamentals@framework
 *
 * Per-sales-channel configuration card for the agentic discovery endpoints
 * (`/agents.md`, `/llms.txt`, `/llms-full.txt`, `/sitemap_agentic_discovery.xml`).
 *
 * Loads or creates an `agentic_discovery_sales_channel_config` entity for the
 * given sales channel and saves it via its own button — independent of the
 * parent sales-channel save lifecycle, so toggling discovery does not require
 * editing unrelated channel settings.
 */

import template from './sw-agentic-discovery-config.html.twig';

const { Mixin, Context } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        salesChannel: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            config: null,
            isLoading: false,
            isSaving: false,
        };
    },

    computed: {
        configRepository() {
            return this.repositoryFactory.create('agentic_discovery_sales_channel_config');
        },

        hasConfig() {
            return this.config !== null;
        },

        editingDisabled() {
            return this.disabled || this.isLoading || this.isSaving || !this.acl.can('sales_channel.editor');
        },
    },

    watch: {
        salesChannel: {
            immediate: true,
            deep: false,
            handler(newSalesChannel) {
                if (newSalesChannel?.id) {
                    this.loadConfig();
                }
            },
        },
    },

    methods: {
        async loadConfig() {
            if (!this.salesChannel?.id) {
                return;
            }

            this.isLoading = true;
            try {
                const criteria = new Criteria(1, 1);
                criteria.addFilter(Criteria.equals('salesChannelId', this.salesChannel.id));

                const result = await this.configRepository.search(criteria, Context.api);

                if (result.length > 0) {
                    this.config = result.first();
                    return;
                }

                this.config = this.configRepository.create(Context.api);
                this.config.salesChannelId = this.salesChannel.id;
                this.config.active = true;
                this.config.exposeAgentsMd = true;
                this.config.exposeLlmsTxt = true;
                this.config.exposeLlmsFullTxt = true;
                this.config.exposeAgenticSitemap = true;
                this.config.customAgentRules = null;
                this.config.customSections = null;
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.agenticDiscovery.messageLoadError'),
                });
            } finally {
                this.isLoading = false;
            }
        },

        async onSave() {
            if (!this.hasConfig) {
                return;
            }

            this.isSaving = true;
            try {
                await this.configRepository.save(this.config, Context.api);
                this.createNotificationSuccess({
                    message: this.$t('sw-sales-channel.agenticDiscovery.messageSaveSuccess'),
                });
                await this.loadConfig();
            } catch (_error) {
                this.createNotificationError({
                    message: this.$t('sw-sales-channel.agenticDiscovery.messageSaveError'),
                });
            } finally {
                this.isSaving = false;
            }
        },

        previewUrl(path) {
            const domain = this.salesChannel?.domains?.first?.();
            const base = domain?.url ?? '';
            return base.replace(/\/+$/g, '') + path;
        },
    },
};
