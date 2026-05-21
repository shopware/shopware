/**
 * @sw-package fundamentals@framework
 *
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Per-Sales-Channel UCP configuration screen. Three tabs: General (capabilities
 * + transports), Keys (signing key management), Profile Preview (read-only).
 */
import template from './sw-settings-ucp-detail.html.twig';

const { Mixin } = Shopware;

const CAPABILITY_GROUPS = [
    {
        id: 'catalog',
        items: [
            { id: 'dev.ucp.shopping.catalog.search', labelKey: 'catalogSearch', helpKey: 'catalogSearchHelp', type: 'root' },
            { id: 'dev.ucp.shopping.catalog.lookup', labelKey: 'catalogLookup', helpKey: 'catalogLookupHelp', type: 'root' },
        ],
    },
    {
        id: 'commerce',
        items: [
            { id: 'dev.ucp.shopping.cart', labelKey: 'cart', helpKey: 'cartHelp', type: 'root' },
            { id: 'dev.ucp.shopping.checkout', labelKey: 'checkout', helpKey: 'checkoutHelp', type: 'root' },
            { id: 'dev.ucp.shopping.order', labelKey: 'order', helpKey: 'orderHelp', type: 'root' },
        ],
    },
    {
        id: 'extensions',
        items: [
            { id: 'dev.ucp.shopping.discount', labelKey: 'discount', helpKey: 'discountHelp', type: 'extension' },
            { id: 'dev.ucp.shopping.fulfillment', labelKey: 'fulfillment', helpKey: 'fulfillmentHelp', type: 'extension' },
            { id: 'dev.ucp.shopping.buyer_consent', labelKey: 'buyerConsent', helpKey: 'buyerConsentHelp', type: 'extension' },
            { id: 'dev.ucp.shopping.loyalty', labelKey: 'loyalty', helpKey: 'loyaltyHelp', type: 'extension' },
        ],
    },
    {
        id: 'identity',
        items: [
            { id: 'dev.ucp.common.identity_linking', labelKey: 'identityLinking', helpKey: 'identityLinkingHelp', type: 'root' },
        ],
    },
];

const ALL_TRANSPORTS = [
    { id: 'rest', labelKey: 'rest' },
    { id: 'mcp', labelKey: 'mcp' },
];

// `strict` is intentionally listed first so it is the default highlighted
// option in `sw-single-select` (which keys off the first option for unset
// values). `off` is dev-only and tagged in the label so a misclick is hard.
const SIGNATURE_POLICY_OPTIONS = [
    { id: 'strict', labelKey: 'strict' },
    { id: 'log', labelKey: 'log' },
    { id: 'off', labelKey: 'off' },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['ucpAdminService', 'repositoryFactory'],

    mixins: [Mixin.getByName('notification')],

    props: {
        salesChannelId: { type: String, required: true },
    },

    data() {
        return {
            isLoading: true,
            isSaving: false,
            isRotatingKey: false,
            config: null,
            keys: [],
            profilePreview: null,
            capabilityGroups: CAPABILITY_GROUPS,
            allTransports: ALL_TRANSPORTS,
            activeTab: 'general',
            salesChannelName: '',
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
        canRotateKey() {
            return Shopware.Service('acl').can('ucp.key_rotator');
        },
        localizedTransports() {
            return this.allTransports.map((transport) => ({
                ...transport,
                label: this.$t(`sw-settings-ucp.detail.transportOptions.${transport.labelKey}`),
            }));
        },
        localizedSignaturePolicyOptions() {
            return SIGNATURE_POLICY_OPTIONS.map((option) => ({
                ...option,
                label: this.$t(`sw-settings-ucp.detail.signaturePolicyOptions.${option.labelKey}`),
            }));
        },
    },

    metaInfo() {
        return { title: this.salesChannelName || 'UCP Configuration' };
    },

    created() {
        this.loadAll();
    },

    methods: {
        async loadAll() {
            this.isLoading = true;
            try {
                const [sc, cfg, keys] = await Promise.all([
                    this.salesChannelRepository.get(this.salesChannelId, Shopware.Context.api),
                    this.ucpAdminService.getConfig(this.salesChannelId),
                    this.ucpAdminService.listKeys(this.salesChannelId).catch(() => ({ items: [] })),
                ]);
                this.salesChannelName = sc?.name ?? '';
                this.config = cfg;
                this.keys = keys.items ?? [];
            } catch (e) {
                this.createNotificationError({ message: e.message });
            } finally {
                this.isLoading = false;
            }
        },

        async save() {
            this.isSaving = true;
            try {
                await this.ucpAdminService.writeConfig(this.salesChannelId, this.config);
                this.createNotificationSuccess({
                    message: this.$t('sw-settings-ucp.detail.saved'),
                });
                await this.loadAll();
            } catch (e) {
                this.createNotificationError({ message: e.message });
            } finally {
                this.isSaving = false;
            }
        },

        async rotateKey() {
            this.isRotatingKey = true;
            try {
                await this.ucpAdminService.createKey(this.salesChannelId, { algorithm: 'ES256', rotate: true });
                this.createNotificationSuccess({
                    message: this.$t('sw-settings-ucp.detail.keyRotated'),
                });
                const refreshed = await this.ucpAdminService.listKeys(this.salesChannelId);
                this.keys = refreshed.items ?? [];
            } catch (e) {
                this.createNotificationError({ message: e.message });
            } finally {
                this.isRotatingKey = false;
            }
        },

        async retireKey(kid) {
            try {
                await this.ucpAdminService.retireKey(this.salesChannelId, kid);
                const refreshed = await this.ucpAdminService.listKeys(this.salesChannelId);
                this.keys = refreshed.items ?? [];
            } catch (e) {
                this.createNotificationError({ message: e.message });
            }
        },

        async loadProfilePreview() {
            try {
                this.profilePreview = await this.ucpAdminService.previewProfile(this.salesChannelId);
            } catch (e) {
                this.createNotificationError({ message: e.message });
            }
        },

        toggleCapability(id) {
            if (!this.config) return;
            const list = this.config.enabledCapabilities ?? [];
            const idx = list.indexOf(id);
            if (idx >= 0) {
                list.splice(idx, 1);
            } else {
                list.push(id);
            }
            this.config.enabledCapabilities = [...list];
        },

        isEnabled(id) {
            return (this.config?.enabledCapabilities ?? []).includes(id);
        },
    },
};
