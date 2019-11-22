import template from './sw-plugin-updates-grid.html.twig';
import './sw-plugin-updates-grid.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-plugin-updates-grid', {
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
            limit: 25,
            updates: [],
            isLoading: false,
            disableRouteParams: false,
            updateQueue: [],
            showLoginModal: false,
            isLoggedIn: false
        };
    },

    watch: {
        '$root.$i18n.locale'() {
            this.getList();
        },

        updateQueue() {
            this.updateQueue.forEach((update) => {
                update.downloadAction.then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                        message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                    });
                    this.getList();
                    this.$root.$emit('updates-refresh');
                    this.updateQueue = this.updateQueue.filter(queueObj => queueObj.pluginName !== update.pluginName);
                }).catch((exception) => {
                    if (exception.response && exception.response.data && exception.response.data.errors) {
                        const unauthorized = exception.response.data.errors.find((error) => {
                            return parseInt(error.code, 10) === 401 || error.code === 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
                        });
                        if (unauthorized) {
                            this.openLoginModal();
                            this.updateQueue = this.updateQueue.filter((queueObj) => {
                                return queueObj.pluginName !== update.pluginName;
                            });
                            return;
                        }
                    }
                    this.handleErrorResponse(exception);
                    this.updateQueue = this.updateQueue.filter(queueObj => queueObj.pluginName !== update.pluginName);
                });
            });
        }
    },

    methods: {
        onUpdate(pluginName) {
            const queueObj = {
                pluginName: pluginName,
                downloadAction: this.storeService.downloadPlugin(pluginName)
            };
            this.updateQueue.push(queueObj);
        },

        updateAll() {
            this.updates.forEach((update) => {
                const queueObj = {
                    pluginName: update.name,
                    downloadAction: this.storeService.downloadPlugin(update.name)
                };
                this.updateQueue.push(queueObj);
            });
        },

        cancelUpdates() {
            this.updateQueue = [];
        },

        isUpdating(pluginName) {
            return this.updateQueue.some(element => element.pluginName === pluginName);
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
        },

        getList() {
            this.isLoading = true;
            this.storeService.getUpdateList().then((data) => {
                this.updates = data.items;
                this.total = data.total;
                this.isLoading = false;
                this.$root.$emit('updates-refresh', this.total);
            }).catch((exception) => {
                this.handleErrorResponse(exception);
                this.isLoading = false;
            });
        }
    }
});
