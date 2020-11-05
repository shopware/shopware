import template from './sw-app-actions.html.twig';
import './sw-app-actions.scss';

const { Component, Mixin, Utils } = Shopware;

Component.register('sw-app-actions', {
    template,

    inject: ['feature', 'appActionButtonService'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            actions: [],
            matchedRoutes: []
        };
    },

    computed: {
        entity() {
            return Utils.get(this.$route, 'meta.$module.entity');
        },

        view() {
            const matchedRoute = this.matchedRoutes.filter((match) => {
                return !!Utils.get(match, 'meta.appSystem.view');
            }).pop();

            return Utils.get(matchedRoute, 'meta.appSystem.view');
        },

        areActionsAvailable() {
            return !!this.actions
                && this.actions.length > 0
                && this.params.length > 0;
        },

        params() {
            return Shopware.State.get('shopwareApps').selectedIds;
        }
    },

    watch: {
        $route: {
            immediate: true,
            handler() {
                this.matchedRoutes = this.$router.currentRoute.matched;
                this.loadActions();
            }
        }
    },

    methods: {
        runAction(actionId) {
            this.appActionButtonService.runAction(actionId, { ids: this.params });
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
                    message: this.$tc('sw-app.component.sw-app-actions.messageErrorFetchButtons')
                });
            }
        }
    }
});
