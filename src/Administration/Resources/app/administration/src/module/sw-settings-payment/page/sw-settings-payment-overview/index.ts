import type CriteriaType from 'src/core/data/criteria.data';
import type Repository from 'src/core/data/repository.data';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type { MetaInfo } from 'vue-meta';
import type { PaymentOverviewCard } from '../../state/overview-cards.store';
import template from './sw-settings-payment-overview.html.twig';
import './sw-settings-payment-overview.scss';

/**
 * @package checkout
 */

interface PaymentMethodEntity extends Entity {
    active: boolean;
    position: number;
    formattedHandlerIdentifier: string;
    translated: {
        name: string;
    }
}

interface PaymentMethodCard {
    id: string;
    hasCustomCard: boolean;
    component?: string;
    positionId: string;
    position: number;
    paymentMethod?: PaymentMethodEntity;
    paymentMethods?: Array<PaymentMethodEntity>;
}

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data(): {
        isLoading: boolean,
        showSortingModal: boolean,
        paymentMethods: Array<PaymentMethodEntity>,
        } {
        return {
            paymentMethods: [],
            isLoading: false,
            showSortingModal: false,
        };
    },

    metaInfo(): MetaInfo {
        return {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            title: this.$createTitle() as string,
        };
    },

    computed: {
        customCards(): PaymentOverviewCard[] {
            return Shopware.State.get('paymentOverviewCardState').cards ?? [];
        },

        paymentMethodRepository(): Repository {
            return this.repositoryFactory.create('payment_method');
        },

        paymentMethodCriteria(): CriteriaType {
            const criteria = new Criteria(1, 500);

            criteria.addAssociation('media');
            criteria.addSorting(Criteria.sort('position', 'ASC'));

            return criteria;
        },

        isEmpty(): boolean {
            return !this.isLoading && this.paymentMethods.length === 0;
        },

        paymentMethodCards(): PaymentMethodCard[] {
            if (this.paymentMethods.length === 0) {
                return [];
            }

            const paymentMethodCards = [];
            let paymentMethods = cloneDeep(this.paymentMethods);

            this.customCards.forEach((customCard: PaymentOverviewCard) => {
                const customPaymentMethods = paymentMethods
                    .filter(pm => customCard.paymentMethodHandlers.includes(pm.formattedHandlerIdentifier));

                if (customPaymentMethods.length === 0) {
                    return;
                }

                paymentMethodCards.push(<PaymentMethodCard>{
                    id: customPaymentMethods[0].id,
                    hasCustomCard: true,
                    component: customCard.component,
                    position: Math.min(...customPaymentMethods.map(pm => pm.position)),
                    positionId: customCard.positionId,
                    paymentMethods: customPaymentMethods,
                });

                paymentMethods = paymentMethods
                    .filter(pm => !customCard.paymentMethodHandlers.includes(pm.formattedHandlerIdentifier));
            });

            paymentMethodCards.push(...paymentMethods.map(paymentMethod => <PaymentMethodCard>{
                id: paymentMethod.id,
                hasCustomCard: false,
                paymentMethod,
                position: paymentMethod.position,
                positionId: '',
            }));

            return paymentMethodCards.sort((a: PaymentMethodCard, b: PaymentMethodCard) => {
                return a.position - b.position;
            });
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.loadPaymentMethods();
        },

        loadPaymentMethods(): void {
            this.isLoading = true;

            this.paymentMethodRepository.search(this.paymentMethodCriteria).then((items: EntityCollection) => {
                this.paymentMethods = items as unknown as Array<PaymentMethodEntity>;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId: string): void {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.loadPaymentMethods();
        },

        togglePaymentMethodActive(paymentMethod: PaymentMethodEntity): void {
            const paymentMethodEntity = this.paymentMethods
                .find((pm: PaymentMethodEntity) => pm.id === paymentMethod.id) as PaymentMethodEntity;
            paymentMethodEntity.active = paymentMethod.active;

            this.paymentMethodRepository.save(paymentMethodEntity).then(() => {
                this.loadPaymentMethods();
                this.showActivationSuccessNotification(paymentMethodEntity.translated.name, paymentMethodEntity.active);
            }).catch(() => {
                this.showActivationErrorNotification(paymentMethodEntity.translated.name, paymentMethodEntity.active);
                this.$nextTick(() => {
                    paymentMethodEntity.active = !paymentMethodEntity.active;
                });
            });
        },

        showActivationSuccessNotification(name: string, active: boolean) {
            const message = active ?
                this.$tc('sw-settings-payment.overview.notification.activationSuccess', 0, { name }) :
                this.$tc('sw-settings-payment.overview.notification.deactivationSuccess', 0, { name });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.createNotificationSuccess({ message });
        },

        showActivationErrorNotification(name: string, active: boolean) {
            const message = active ?
                this.$tc('sw-settings-payment.overview.notification.activationError', 0, { name }) :
                this.$tc('sw-settings-payment.overview.notification.deactivationError', 0, { name });

            // eslint-disable-next-line @typescript-eslint/no-unsafe-call
            this.createNotificationError({ message });
        },
    },
});
