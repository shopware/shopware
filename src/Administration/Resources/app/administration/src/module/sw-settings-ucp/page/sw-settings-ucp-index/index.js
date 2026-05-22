/**
 * @sw-package fundamentals@framework
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP configuration overview — lists all Sales Channels with their current
 * UCP enablement state and offers an entry into each detail view.
 */
import template from './sw-settings-ucp-index.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['ucpAdminService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            items: [],
        };
    },

    metaInfo() {
        return {
            title: this.$t('sw-settings-ucp.general.mainMenuItemGeneral'),
        };
    },

    created() {
        this.loadItems();
    },

    methods: {
        async loadItems() {
            this.isLoading = true;
            try {
                const response = await this.ucpAdminService.listSalesChannels();
                this.items = response.items ?? [];
            } catch (e) {
                this.createNotificationError({ message: e.message });
            } finally {
                this.isLoading = false;
            }
        },

        editItem(item) {
            this.$router.push({
                name: 'sw.settings.ucp.detail',
                params: { salesChannelId: item.salesChannelId },
            });
        },

        capabilityCount(item) {
            return item.enabledCapabilities?.length ?? 0;
        },

        statusVariant(item) {
            // Mirror the three-state logic of statusLabel so the badge colour
            // can never disagree with the label text. A "Not configured" label
            // in a green "positive" pill would be visually misleading and
            // could surface if a future change to the controller decoupled
            // `active` from `configured`.
            if (!item.configured) {
                return 'neutral';
            }
            return item.active ? 'positive' : 'neutral';
        },

        statusLabel(item) {
            if (!item.configured) {
                return this.$t('sw-settings-ucp.status.notConfigured');
            }
            return item.active ? this.$t('sw-settings-ucp.status.active') : this.$t('sw-settings-ucp.status.inactive');
        },
    },
};
