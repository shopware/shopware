import template from './sw-settings-shopware-updates-index.html.twig';
import './sw-settings-shopware-updates-index.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-settings-shopware-updates-index', {
    template,

    inject: ['updateService'],
    mixins: [
        Mixin.getByName('notification')
    ],
    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isSearchingForUpdates: false,
            updateModalShown: false,
            updateInfo: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    methods: {
        searchForUpdates() {
            this.isSearchingForUpdates = true;
            this.updateService.checkForUpdates().then(response => {
                this.isSearchingForUpdates = false;

                if (response.version) {
                    this.updateInfo = response;
                    this.updateModalShown = true;
                } else {
                    this.createNotificationSuccess({
                        title: this.$t('sw-settings-shopware-updates.notifications.title'),
                        message: this.$t('sw-settings-shopware-updates.notifications.alreadyUpToDate')
                    });
                }
            });
        },

        openUpdateWizard() {
            this.updateModalShown = false;

            this.$nextTick(() => {
                this.$router.push({ name: 'sw.settings.shopware.updates.wizard' });
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
                this.isLoading = false;
                this.createNotificationError({
                    title: this.$tc('sw-settings-store.general.titleSaveError'),
                    message: err
                });
            });
        }
    },

    computed: {
        shopwareVersion() {
            return Shopware.Context.app.config.version;
        }
    }
});
