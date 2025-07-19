import template from './sw-settings-shopware-updates-index.html.twig';
import './sw-settings-shopware-updates-index.scss';

const { Component, Mixin } = Shopware;

/**
 * @sw-package framework
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: ['updateService'],

    mixins: [
        Mixin.getByName('notification'),
    ],
    data(): {
        isLoading: boolean;
        isSaveSuccessful: boolean;
        isSearchingForUpdates: boolean;
        updateModalShown: boolean;
        updateInfo: null | {
            version: unknown;
            changelog: unknown;
        };
    } {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            isSearchingForUpdates: false,
            updateModalShown: false,
            updateInfo: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        shopwareVersion() {
            return Shopware.Context.app.config.version;
        },
    },

    methods: {
        searchForUpdates() {
            this.isSearchingForUpdates = true;
            void this.updateService.checkForUpdates().then((response) => {
                this.isSearchingForUpdates = false;

                if (response.version) {
                    this.updateInfo = response;
                    this.updateModalShown = true;
                } else {
                    this.createNotificationInfo({
                        message: this.$tc('sw-settings-shopware-updates.notifications.alreadyUpToDate'),
                    });
                }
            });
        },

        openUpdateWizard() {
            this.updateModalShown = false;

            void this.$nextTick(() => {
                void this.$router.push({
                    name: 'sw.settings.shopware.updates.wizard',
                });
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call, @typescript-eslint/no-unsafe-member-access
            this.$refs.systemConfig
                .saveAll()
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                .then(() => {
                    this.isLoading = false;
                    this.isSaveSuccessful = true;
                })
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                .catch((err: string) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: err,
                    });
                });
        },

        onLoadingChanged(loading: boolean) {
            this.isLoading = loading;
        },
    },
});
