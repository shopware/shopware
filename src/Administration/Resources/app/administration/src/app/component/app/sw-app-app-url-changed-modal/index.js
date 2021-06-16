import template from './sw-app-app-url-changed-modal.html.twig';
import './sw-app-app-url-changed-modal.scss';

const { Component } = Shopware;

Component.register('sw-app-app-url-changed-modal', {
    template,

    inject: ['appUrlChangeService'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        /**
         * @var {newUrl: string, oldUrl: string}
         */
        urlDiff: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            strategies: [],
            selectedStrategy: null,
            isLoading: true,
        };
    },

    created() {
        this.appUrlChangeService
            .fetchResolverStrategies()
            .then((strategies) => {
                this.strategies = strategies;
                this.selectedStrategy = strategies[0];
            })
            .then(() => {
                this.isLoading = false;
            });
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },

        setSelectedStrategy(strategy) {
            this.selectedStrategy = strategy;
        },

        isSelected({ name }) {
            return !!this.selectedStrategy && this.selectedStrategy.name === name;
        },

        getStrategyLabel({ name }) {
            return this.$tc(`sw-app.component.sw-app-app-url-changed-modal.${name}.name`);
        },

        getStrategyDescription({ name }) {
            return this.$tc(`sw-app.component.sw-app-app-url-changed-modal.${name}.description`);
        },

        getActiveStyle({ name }) {
            return {
                'sw-app-app-url-changed-modal__content-migration-strategy--active': name === this.selectedStrategy.name,
            };
        },

        confirm() {
            this.appUrlChangeService.resolveUrlChange(this.selectedStrategy)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-app.component.sw-app-app-url-changed-modal.success'),
                    });
                })
                .then(this.closeModal)
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-app.component.sw-app-app-url-changed-modal.error'),
                    });
                });
        },
    },
});
