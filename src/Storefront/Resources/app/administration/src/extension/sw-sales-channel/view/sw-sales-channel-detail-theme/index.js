import template from './sw-sales-channel-detail-theme.html.twig';
import './sw-sales-channel-detail-theme.scss';

const { Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;

const PENDING_THEME_CONFIG_DOMAIN = 'storefront';
const PENDING_THEME_CONFIG_KEY = 'storefront.pendingThemeAssignment';
const PENDING_CHECK_INTERVAL = 10000;

/**
 * @deprecated tag:v6.8.0 - Will be @private
 * @sw-package discovery
 */
export default {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder'),
    ],

    inject: [
        'repositoryFactory',
        'themeService',
        'systemConfigApiService',
        'acl',
    ],

    props: {
        salesChannel: {
            required: true,
        },
    },

    data() {
        return {
            theme: null,
            pendingTheme: null,
            pendingCheckTimeoutId: null,
            activeCheckId: 0,
            showThemeSelectionModal: false,
            showChangeModal: false,
            newThemeId: null,
            isLoading: false,
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        },

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        pendingTooltip() {
            if (!this.pendingTheme) {
                return '';
            }

            return this.theme
                ? this.$t('sales-channel-theme.pendingAssignment.description', {
                      liveThemeName: this.theme.name,
                      pendingThemeName: this.pendingTheme.name,
                  })
                : this.$t('sales-channel-theme.pendingAssignment.descriptionNoLiveTheme', {
                      pendingThemeName: this.pendingTheme.name,
                  });
        },
    },

    watch: {
        'salesChannel.extensions.themes': {
            deep: true,
            handler() {
                this.getTheme(this.salesChannel?.extensions?.themes[0]?.id);
            },
        },
        salesChannel() {
            this.checkPendingAssignment();
        },
    },

    created() {
        this.createdComponent();
    },

    beforeUnmount() {
        this.beforeUnmountComponent();
    },

    methods: {
        createdComponent() {
            if (this.salesChannel?.extensions?.themes[0]) {
                this.theme = this.salesChannel.extensions.themes[0];
                this.getTheme(this.theme.id);
            }

            this.checkPendingAssignment();
        },

        beforeUnmountComponent() {
            // invalidate any in-flight check so a late-resolving read cannot re-schedule polling
            this.activeCheckId += 1;
            this.clearPendingCheck();
        },

        async getTheme(themeId) {
            if (!themeId) {
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('previewMedia');

            this.theme = await this.themeRepository.get(themeId, Shopware.Context.api, criteria);
        },

        async checkPendingAssignment() {
            if (!this.salesChannel?.id) {
                return;
            }

            // Tag this run so a result resolving after unmount or a sales-channel change is discarded.
            this.activeCheckId += 1;
            const checkId = this.activeCheckId;

            try {
                const [
                    pendingThemeId,
                    liveThemeId,
                ] = await Promise.all([
                    this.loadPendingThemeId(),
                    this.loadLiveThemeId(),
                ]);

                if (checkId !== this.activeCheckId) {
                    return;
                }

                if (pendingThemeId && pendingThemeId !== liveThemeId) {
                    if (this.pendingTheme?.id !== pendingThemeId) {
                        this.pendingTheme = await this.themeRepository.get(pendingThemeId, Shopware.Context.api);

                        if (checkId !== this.activeCheckId) {
                            return;
                        }
                    }

                    this.schedulePendingCheck();

                    return;
                }

                this.clearPendingCheck();
                this.pendingTheme = null;

                if (liveThemeId && liveThemeId !== this.theme?.id) {
                    await this.getTheme(liveThemeId);
                }
            } catch {
                if (checkId !== this.activeCheckId) {
                    return;
                }

                // best-effort indicator: on any read error stop polling and hide the spinner
                this.clearPendingCheck();
                this.pendingTheme = null;
            }
        },

        async loadPendingThemeId() {
            // Query the parent domain: getValues() matches "<domain>.%", so the full key would
            // return nothing. Read the exact key out of the returned "storefront.*" map.
            const values = await this.systemConfigApiService.getValues(PENDING_THEME_CONFIG_DOMAIN, this.salesChannel.id);

            return values?.[PENDING_THEME_CONFIG_KEY] ?? null;
        },

        async loadLiveThemeId() {
            const criteria = new Criteria();
            criteria.addAssociation('themes');

            const salesChannel = await this.salesChannelRepository.get(this.salesChannel.id, Shopware.Context.api, criteria);

            return salesChannel?.extensions?.themes?.[0]?.id ?? null;
        },

        schedulePendingCheck() {
            this.clearPendingCheck();
            this.pendingCheckTimeoutId = setTimeout(() => this.checkPendingAssignment(), PENDING_CHECK_INTERVAL);
        },

        clearPendingCheck() {
            if (this.pendingCheckTimeoutId) {
                clearTimeout(this.pendingCheckTimeoutId);
                this.pendingCheckTimeoutId = null;
            }
        },

        openThemeModal() {
            if (!this.acl.can('sales_channel.editor')) {
                return;
            }

            this.showThemeSelectionModal = true;
        },

        closeThemeModal() {
            this.showThemeSelectionModal = false;
        },

        openInThemeManager() {
            if (!this.theme) {
                this.$router.push({ name: 'sw.theme.manager.index' });
            } else {
                this.$router.push({ name: 'sw.theme.manager.detail', params: { id: this.theme.id } });
            }
        },

        async onChangeTheme(themeId) {
            this.showThemeSelectionModal = false;

            await this.getTheme(themeId);
            this.salesChannel.extensions.themes[0] = this.theme;
        },
    },
};
