import type { PropType } from 'vue';
import template from './sw-order-create-options.html.twig';
import './sw-order-create-options.scss';
import type { ContextSwitchParameters, Customer, Currency } from '../../order.types';
import type Repository from '../../../../core/data/repository.data';

const { Component, State } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],

    props: {
        promotionCodes: {
            type: Array as PropType<string[]>,
            required: true,
        },

        disabledAutoPromotions: {
            type: Boolean as PropType<boolean>,
            required: true,
        },

        context: {
            type: Object as PropType<ContextSwitchParameters>,
            required: true,
        },
    },

    data(): {
        promotionCodeTags: string[],
        } {
        return {
            promotionCodeTags: this.promotionCodes,
        };
    },

    computed: {
        salesChannelId: {
            get(): string {
                return State.get('swOrder').context.salesChannel.id ?? '';
            },

            set(salesChannelId: string) {
                State.get('swOrder').context.salesChannel.id = salesChannelId;
            },
        },

        testOrder: {
            get(): boolean {
                return State.get('swOrder').testOrder;
            },

            set(testOrder: boolean) {
                State.commit('swOrder/setTestOrder', testOrder);
            },
        },

        salesChannelCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        shippingMethodCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('active', 1));

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        paymentMethodCriteria() {
            const criteria = new Criteria(1, 25);
            criteria.addFilter(Criteria.equals('active', 1));
            criteria.addFilter(Criteria.equals('afterOrderEnabled', 1));

            if (this.salesChannelId) {
                criteria.addFilter(Criteria.equals('salesChannels.id', this.salesChannelId));
            }

            return criteria;
        },

        currencyRepository(): Repository {
            return this.repositoryFactory.create('currency');
        },


        customer(): Customer | null {
            return State.get('swOrder').customer;
        },

        currency(): Currency {
            return State.get('swOrder').context.currency;
        },

        isCartTokenAvailable(): boolean {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return State.getters['swOrder/isCartTokenAvailable'] as boolean;
        },
    },

    watch: {
        'context.currencyId': {
            handler() {
                void this.getCurrency();
            },
        },
    },

    methods: {
        getCurrency(): Promise<void> {
            if (!this.context.currencyId) {
                return Promise.resolve();
            }

            return this.currencyRepository.get(this.context.currencyId).then((currency) => {
                State.commit('swOrder/setCurrency', currency);
            });
        },

        validatePromotions(searchTerm: string): string | boolean {
            if (searchTerm.length < 0) {
                return false;
            }

            const isExist = this.promotionCodes.find((code: string) => code === searchTerm);

            if (isExist) {
                return false;
            }

            return searchTerm;
        },

        toggleAutoPromotions(value: boolean) {
            this.$emit('auto-promotions-toggle', value);
        },

        changePromotionCodes(value: string[]) {
            this.$emit('promotions-change', value);
        },
    },
});
