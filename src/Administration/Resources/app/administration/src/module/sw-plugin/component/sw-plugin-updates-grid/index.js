import template from './sw-plugin-updates-grid.html.twig';
import './sw-plugin-updates-grid.scss';

const { Component, Mixin, State } = Shopware;
const storeService = Shopware.Service('storeService');

Component.register('sw-plugin-updates-grid', {
    template,

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
            limit: 25,
            isLoading: false,
            updateQueue: [],
            showLoginModal: false,
            unsubscribeStore: null,
            currentDownload: null
        };
    },

    computed: {
        updates() {
            return State.get('swPlugin').updates;
        },

        total() {
            return State.get('swPlugin').availableUpdates;
        },

        isLoggedIn() {
            return State.get('swPlugin').loginStatus;
        },

        updateColumns() {
            return [{
                property: 'label',
                label: 'sw-plugin.list.columnPlugin',
                align: 'center'
            }, {
                property: 'changelog',
                width: '*',
                label: 'sw-plugin.list.columnChangelog'
            }, {
                property: 'action-update',
                align: 'center'
            }];
        },

        noUpdates() {
            return !this.isLoading && this.total < 1;
        }
    },

    created() {
        this.subscribeUpdateAction();
    },

    beforeDestroy() {
        if (typeof this.unsubscribeStore === 'function') {
            this.unsubscribeStore();
        }
    },

    methods: {
        onUpdate(update) {
            this.addToQueue(update);
            this.startDownload();
        },

        updateAll() {
            this.updates.forEach((update) => {
                this.addToQueue(update);
            });
            this.startDownload();
        },

        addToQueue(update) {
            if (this.updateQueue.some((scheduled) => scheduled === update)) {
                return;
            }

            this.updateQueue.push(update);
        },

        cancelUpdates() {
            this.currentDownload = null;
            this.updateQueue = [];
        },

        isUpdating(update) {
            return this.currentDownload === update ||
                   this.updateQueue.some(scheduled => scheduled === update);
        },

        openLoginModal() {
            this.showLoginModal = true;
        },

        closeModal() {
            this.showLoginModal = false;
        },

        getList() {
            return State.dispatch('swPlugin/fetchAvailableUpdates');
        },

        subscribeUpdateAction() {
            this.unsubscribeStore = State.subscribeAction({
                before: ({ type }) => {
                    if (type === 'swPlugin/fetchAvailableUpdates') {
                        this.isLoading = true;
                    }
                },
                after: ({ type }) => {
                    if (type === 'swPlugin/fetchAvailableUpdates') {
                        this.isLoading = false;
                    }
                }
            });
        },

        startDownload() {
            if (this.currentDownload !== null) {
                return Promise.resolve();
            }

            if (this.updateQueue.length <= 0) {
                return this.getList();
            }

            this.currentDownload = this.updateQueue.shift();
            return storeService.downloadPlugin(this.currentDownload.name, this.currentDownload.integrated)
                .then(() => {
                    this.createNotificationSuccess({
                        title: this.$tc('sw-plugin.updates.titleUpdateSuccess'),
                        message: this.$tc('sw-plugin.updates.messageUpdateSuccess')
                    });

                    this.currentDownload = null;
                    return this.startDownload();
                }).catch((errorResponse) => {
                    this.cancelUpdates();
                    this.checkUnauthorized(errorResponse).then(() => {
                        throw errorResponse;
                    });
                });
        },

        checkUnauthorized({ response }) {
            if (response && response.data && response.data.errors) {
                const unauthorized = response.data.errors.find((error) => {
                    return parseInt(error.code, 10) === 401 || error.code === 'FRAMEWORK__STORE_TOKEN_IS_MISSING';
                });
                if (unauthorized) {
                    this.openLoginModal();
                    return State.dispatch('swPlugin/logoutShopwareUser');
                }
            }
            return Promise.resolve();
        }
    }
});
