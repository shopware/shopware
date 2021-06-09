import template from './sw-app-actions.html.twig';
import './sw-app-actions.scss';

const { Component, Mixin } = Shopware;

const actionTypeConstants = Object.freeze({
    ACTION_SHOW_NOTITFICATION: 'notification',
    ACTION_RELOAD_DATA: 'reload',
    ACTION_OPEN_NEW_TAB: 'openNewTab',
});

Component.register('sw-app-actions', {
    template,

    inject: ['feature', 'appActionButtonService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            actions: [],
            matchedRoutes: [],
        };
    },

    computed: {
        entity() {
            return this.$route?.meta?.$module?.entity;
        },

        view() {
            const matchedRoute = this.matchedRoutes.filter((match) => {
                return !!match?.meta?.appSystem?.view;
            }).pop();

            return matchedRoute?.meta?.appSystem?.view;
        },

        areActionsAvailable() {
            return !!this.actions
                && this.actions.length > 0
                && this.params.length > 0;
        },

        params() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },
    },

    watch: {
        $route: {
            immediate: true,
            handler() {
                this.matchedRoutes = this.$router.currentRoute.matched;
                this.loadActions();
            },
        },
    },

    methods: {
        async runAction(actionId) {
            const { data } = await this.appActionButtonService.runAction(actionId, { ids: this.params });
            const { actionType, redirectUrl, status, message } = data;

            switch (actionType) {
                case actionTypeConstants.ACTION_OPEN_NEW_TAB:
                    window.open(redirectUrl, '_blank');
                    break;
                case actionTypeConstants.ACTION_SHOW_NOTITFICATION:
                    this.createNotification({
                        variant: status,
                        message: message,
                    });
                    break;
                case actionTypeConstants.ACTION_RELOAD_DATA:
                    window.location.reload();
                    break;
                default:
                    break;
            }
        },

        async loadActions() {
            try {
                this.actions = await this.appActionButtonService.getActionButtonsPerView(this.entity, this.view);
            } catch (e) {
                this.actions = [];

                // ignore missing parameter exception for pages without correct view
                if (!!e.name && e.name === 'InvalidActionButtonParameterError') {
                    return;
                }

                this.createNotificationError({
                    message: this.$tc('sw-app.component.sw-app-actions.messageErrorFetchButtons'),
                });
            }
        },
    },
});
