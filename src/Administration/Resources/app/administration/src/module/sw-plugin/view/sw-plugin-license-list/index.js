import template from './sw-plugin-license-list.html.twig';
import './sw-plugin-license-list.scss';

const { Component, Mixin, State } = Shopware;

Component.register('sw-plugin-license-list', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        pageLoading: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            licenses: [],
            isLoading: false,
            showLoginModal: false,
            total: 0
        };
    },

    computed: {
        isLoggedIn() {
            return State.get('swPlugin').loginStatus;
        },

        licensesColumns() {
            return [{
                property: 'name',
                label: 'sw-plugin.license-list.columnName',
                type: 'Text'
            }, {
                property: 'creationDate',
                label: 'sw-plugin.license-list.columnCreationDate',
                type: 'Date'
            }, {
                property: 'type',
                label: 'sw-plugin.license-list.columnType'
            }, {
                property: 'expirationDate',
                label: 'sw-plugin.license-list.columnExpirationDate'
            }, {
                property: 'availableVersion',
                align: 'center'
            }];
        }
    },

    watch: {
        isLoggedIn: {
            immediate: true,
            handler() {
                return this.getList();
            }
        }
    },

    methods: {
        downloadPlugin(pluginName, update = false) {
            this.storeService.downloadPlugin(pluginName).then(() => {
                if (update) {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                        message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                    });
                } else {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.general.titleDownloadSuccess'),
                        message: this.$tc('sw-plugin.general.messageDownloadSuccess')
                    });
                }
                this.getList();
            });
        },

        getExpirationDate(license) {
            if (license.expirationDate) {
                return license.expirationDate;
            }

            if (license.subscription && license.subscription.expirationDate) {
                return license.subscription.expirationDate;
            }

            return null;
        },

        getList() {
            if (!this.isLoggedIn) {
                return Promise.resolve();
            }

            this.isLoading = true;
            return this.storeService.getLicenseList().then(({ items, total }) => {
                this.licenses = items;
                this.total = total;
            }).catch((exception) => {
                if (exception.response && exception.response.data && exception.response.data.errors) {
                    const unauthorized = exception.response.data.errors.find((error) => {
                        return parseInt(error.code, 10) === 401 || error.code === 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
                    });
                    if (unauthorized) {
                        this.openLoginModal();
                    }
                }
            }).finally(() => {
                this.isLoading = false;
            });
        },

        openLoginModal() {
            this.showLoginModal = true;
        },

        closeLoginModal() {
            this.showLoginModal = false;
        }
    }
});
