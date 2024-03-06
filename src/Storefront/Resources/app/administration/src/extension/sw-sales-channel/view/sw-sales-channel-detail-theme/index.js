import template from './sw-sales-channel-detail-theme.html.twig';
import './sw-sales-channel-detail-theme.scss';

/**
 * @package buyers-experience
 */

const { Component, Mixin } = Shopware;
const Criteria = Shopware.Data.Criteria;

Component.register('sw-sales-channel-detail-theme', {
    template,

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    inject: [
        'repositoryFactory',
        'themeService',
        'acl'
    ],

    props: {
        salesChannel: {
            required: true
        }
    },

    data() {
        return {
            theme: null,
            showThemeSelectionModal: false,
            showChangeModal: false,
            newThemeId: null,
            isLoading: false,
        };
    },

    computed: {
        themeRepository() {
            return this.repositoryFactory.create('theme');
        }
    },

    watch: {
        'salesChannel.extensions.themes': {
            deep: true,
            handler() {
                if (!this.salesChannel || !this.salesChannel.extensions || this.salesChannel.extensions.themes.length < 1) {
                    return;
                }

                this.theme = this.salesChannel.extensions.themes[0];

                this.getTheme(this.theme.id);
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.salesChannel ||
                !this.salesChannel.extensions ||
                this.salesChannel.extensions.themes.length < 1) {
                return;
            }

            this.theme = this.salesChannel.extensions.themes[0];
            this.getTheme(this.theme.id);
        },

        async getTheme(themeId) {
            if (themeId === null) {
                return;
            }

            const criteria = new Criteria();
            criteria.addAssociation('previewMedia');

            this.theme = await this.themeRepository.get(themeId, Shopware.Context.api, criteria);
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

        /**
         * @deprecated tag:v6.7.0 - will be removed
         */
        onCloseChangeModal() {
            this.showChangeModal = false;
            this.newThemeId = null;
        },

        /**
         * @deprecated tag:v6.7.0 - will be removed
         */
        onConfirmChange() {
            if (this.newThemeId) {
                this.onThemeSelect(this.newThemeId);
            }

            this.showChangeModal = false;
            this.newThemeId = null;
        },

        /**
         * @deprecated tag:v6.7.0 - will be removed
         */
        onThemeSelect(selectedThemeId) {
            this.isLoading = true;
            this.getTheme(selectedThemeId);
            this.themeService.assignTheme(selectedThemeId, this.salesChannel.id).then(() => {
                this.isLoading = false;
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-theme-manager.general.titleError'),
                    message: this.$tc('sw-theme-manager.general.messageSaveError')
                });
                this.isLoading = false;
            });
        },
    },
});
