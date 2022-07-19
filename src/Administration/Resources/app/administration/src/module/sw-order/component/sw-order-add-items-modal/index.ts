import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type Repository from 'src/core/data/repository.data';
import template from './sw-order-add-items-modal.html.twig';
import './sw-order-add-items-modal.scss';

const { Component, Utils, Mixin } = Shopware;

interface ProductEntity extends Entity {
    name: string,
    amount: number,
    tax: {
        taxRate: number,
    },
    price: [
        {
            gross: number,
            net: number,
        },
    ],
}
interface CustomItem {
    label?: string,
    price?: number,
    quantity?: number,
    tax?: {
        taxRate?: number,
    },
}
interface CreditItem {
    label?: string,
    price?: number,
}
interface LineItemTypes {
    PRODUCT: string,
    CREDIT: string,
    CUSTOM: string,
    PROMOTION: string,
}
interface LineItemPriceTypes {
    ABSOLUTE: string,
    QUANTITY: string,
}

/**
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-add-items-modal', {
    template,

    inject: ['repositoryFactory', 'cartStoreService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        currency: {
            type: Object,
            required: true,
        },
        cart: {
            type: Object as PropType<{ token: string|null }>,
            required: true,
        },
        salesChannelId: {
            type: String,
            required: true,
        },
    },

    data(): {
        products: Array<ProductEntity>,
        customItem: CustomItem,
        credit: CreditItem,
        items: Array<ProductEntity|CustomItem|CreditItem>,
        isLoading: boolean,
        } {
        return {
            products: [],
            customItem: {},
            credit: {},
            items: [],
            isLoading: false,
        };
    },

    computed: {
        orderLineItemRepository(): Repository {
            return this.repositoryFactory.create('order_line_item');
        },

        lineItemTypes(): LineItemTypes {
            return this.cartStoreService.getLineItemTypes();
        },

        lineItemPriceTypes(): LineItemPriceTypes {
            return this.cartStoreService.getLineItemPriceTypes();
        },

        taxStatus(): string {
            return Utils.get(this.cart, 'price.taxStatus', '') as string;
        },
    },

    methods: {
        onSelectProducts(products: Array<ProductEntity>): void {
            this.products = products;
        },

        onClose(): void {
            this.$emit('modal-close');
        },

        async onSave() {
            const productItems = this.products.filter((product) => product.amount > 0);
            if (productItems.length > 0) {
                this.items = [...productItems.map((product) => this.addProduct(product))];
            }

            if (this.isValidItem(this.customItem)) {
                this.items.push(this.addCustomItem(this.customItem));
            }

            if (this.isValidItem(this.credit)) {
                this.items.push(this.addCredit(this.credit));
            }

            this.isLoading = true;
            if (this.items.length <= 0) {
                this.isLoading = false;
                this.$emit('modal-close');

                return;
            }

            try {
                await this.cartStoreService.addMultipleLineItems(this.salesChannelId, this.cart.token, this.items);
                this.isLoading = false;
                this.$emit('modal-save');
            } catch ({ message }) {
                this.isLoading = false;
                // @ts-expect-error - method is defined in mixin
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                void this.createNotificationError({ message });
            }
        },

        addProduct(product: ProductEntity): Record<string, unknown> {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: this.lineItemTypes.PRODUCT,
                label: product.name,
                identifier: product.id,
                quantity: product.amount,
                price: {
                    taxRules: [
                        {
                            taxRate: product.tax.taxRate,
                        },
                    ],
                },
                priceDefinition: {
                    type: this.lineItemPriceTypes.QUANTITY,
                    price: this.taxStatus === 'gross'
                        ? product.price[0].gross
                        : product.price[0].net,
                    taxRules: [
                        {
                            taxRate: product.tax.taxRate,
                        },
                    ],
                },
            };
        },

        addCustomItem(customItem: CustomItem): Record<string, unknown> {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: this.lineItemTypes.CUSTOM,
                label: customItem.label,
                description: 'Custom line item',
                quantity: customItem.quantity,
                priceDefinition: {
                    type: this.lineItemPriceTypes.QUANTITY,
                    price: customItem.price,
                    quantity: customItem.quantity,
                    taxRules: [
                        {
                            taxRate: customItem.tax?.taxRate || 0,
                            percentage: 100,
                        },
                    ],
                },
            };
        },

        addCredit(credit: CreditItem): Record<string, unknown> {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: this.lineItemTypes.CREDIT,
                label: credit.label,
                description: 'Credit line item',
                priceDefinition: {
                    type: this.lineItemPriceTypes.ABSOLUTE,
                    price: credit.price,
                    quantity: 1,
                },
            };
        },

        createNewOrderLineItem(): { versionId: string|null } {
            const item = this.orderLineItemRepository.create();

            return {
                ...item,
                versionId: Shopware.Context.api.liveVersionId,
            };
        },

        isValidItem(item: CustomItem|CreditItem) {
            return item?.label && item?.price;
        },
    },
});
