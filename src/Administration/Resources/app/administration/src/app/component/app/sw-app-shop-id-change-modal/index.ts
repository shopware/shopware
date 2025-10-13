/**
 * @sw-package framework
 */

import type { ShopIdCheck, Strategy } from 'src/core/service/api/shop-id-change.service';
import template from './sw-app-shop-id-change-modal.html.twig';
import './sw-app-shop-id-change-modal.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['shopIdChangeService'],

    emits: ['modal-close'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        shopIdCheck: {
            type: Object as PropType<ShopIdCheck>,
            required: true,
        },
    },

    data() {
        return {
            strategies: [] as Strategy[],
            selectedStrategy: null as Strategy | null,
            isLoading: true,
        };
    },

    created() {
        void this.fetchStrategies();
    },

    methods: {
        async fetchStrategies() {
            const strategies = await this.shopIdChangeService.getChangeStrategies();

            this.strategies = strategies;
            this.selectedStrategy = strategies[0];
            this.isLoading = false;
        },

        closeModal() {
            this.$emit('modal-close');
        },

        setSelectedStrategy(strategy: Strategy) {
            this.selectedStrategy = strategy;
        },

        isSelected({ name }: Strategy) {
            return !!this.selectedStrategy && this.selectedStrategy.name === name;
        },

        getStrategyLabel({ name }: Strategy) {
            return this.$t(`sw-app.component.sw-app-shop-id-change-modal.strategies.${name}.name`);
        },

        getStrategyDescription({ name }: Strategy) {
            return this.$t(`sw-app.component.sw-app-shop-id-change-modal.strategies.${name}.description`);
        },

        getActiveStyle({ name }: Strategy) {
            return {
                'sw-app-shop-id-change-modal__button-strategy--active': name === this.selectedStrategy?.name,
            };
        },

        async confirm() {
            if (!this.selectedStrategy) {
                this.createNotificationError({
                    message: this.$t('sw-app.component.sw-app-shop-id-change-modal.error.no-strategy-selected'),
                });

                return;
            }

            try {
                await this.shopIdChangeService.changeShopId(this.selectedStrategy);

                this.createNotificationSuccess({
                    message: this.$t('sw-app.component.sw-app-shop-id-change-modal.success.shop-id-change-succeeded'),
                });

                window.location.reload();
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-app.component.sw-app-shop-id-change-modal.error.shop-id-change-failed'),
                });
            }
        },

        getHumanReadableFingerprintName(identifier: string) {
            const k = `sw-app.component.sw-app-shop-id-change-modal.fingerprints.${identifier}.label`;
            const t = this.$t(k);

            if (k === t) {
                return identifier;
            }

            return t;
        },

        getFingerprintDescription(identifier: string) {
            const k = `sw-app.component.sw-app-shop-id-change-modal.fingerprints.${identifier}.description`;
            const t = this.$t(k);

            if (k === t) {
                return null;
            }

            return t;
        },
    },
});
