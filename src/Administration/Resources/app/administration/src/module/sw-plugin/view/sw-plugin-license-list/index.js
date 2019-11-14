import template from './sw-plugin-license-list.html.twig';
import './sw-plugin-license-list.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-plugin-license-list', {
    template,

    inject: ['storeService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
        Mixin.getByName('plugin-error-handler')
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
            isLoggedIn: false
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        }
    },

    created() {
        this.createdComponent();
    },

    destroyed() {
        this.destroyedComponent();
    },

    methods: {
        createdComponent() {
            this.$root.$on('plugin-logout', this.getList);
        },

        destroyedComponent() {
            this.$root.$off('plugin-logout', this.getList);
        },

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
                this.$root.$emit('last-updates-refresh');
            });
        },

        getList() {
            this.total = 0;
            this.isLoading = true;
            this.storeService.getLicenseList().then((response) => {
                this.licenses = response.items;
                this.total = response.total;
                this.isLoading = false;
                this.isLoggedIn = true;
            }).catch((exception) => {
                this.isLoading = false;
                this.isLoggedIn = false;
                if (exception.response && exception.response.data && exception.response.data.errors) {
                    const unauthorized = exception.response.data.errors.find((error) => {
                        return parseInt(error.code, 10) === 401 || error.code === 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
                    });
                    if (unauthorized) {
                        this.openLoginModal();
                    }
                }
            });
        },

        openLoginModal() {
            this.showLoginModal = true;
        },

        loginSuccess() {
            this.showLoginModal = false;
            this.getList();
            this.$root.$emit('plugin-login');
        },

        loginAbort() {
            this.showLoginModal = false;
        }
    }
});
