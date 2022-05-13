import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type Repository from 'src/core/data/repository.data';
import template from './sw-order-add-items-modal.html.twig';
import './sw-order-add-items-modal.scss';
import type { Cart, LineItem } from '../../order.types';
import { LineItemType, PriceType } from '../../order.types';

const { Utils, Mixin } = Shopware;

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
    label: string,
    price?: number,
    quantity: number,
    tax?: {
        taxRate?: number,
    },
}
interface CreditItem {
    label: string,
    price?: number,
}

/**
 * @private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Shopware.Component.wrapComponentConfig({
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
            type: Object as PropType<Cart>,
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
        items: Array<LineItem>,
        isLoading: boolean,
        } {
        return {
            products: [],
            customItem: {
                label: '',
                quantity: 1,
            },
            credit: {
                label: '',
            },
            items: [],
            isLoading: false,
        };
    },

    computed: {
        orderLineItemRepository(): Repository {
            return this.repositoryFactory.create('order_line_item');
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
                await this.cartStoreService.addMultipleLineItems(this.salesChannelId, this.cart.token ?? '', this.items);
                this.isLoading = false;
                this.$emit('modal-save');
            } catch ({ message }) {
                this.isLoading = false;
                // @ts-expect-error - method is defined in mixin
                // eslint-disable-next-line @typescript-eslint/no-unsafe-call
                void this.createNotificationError({ message });
            }
        },

        addProduct(product: ProductEntity): LineItem {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: LineItemType.PRODUCT,
                label: product.name,
                identifier: product.id,
                quantity: product.amount,
                // @ts-expect-error
                price: {
                    taxRules: [
                        {
                            taxRate: product.tax.taxRate,
                            percentage: null,
                        },
                    ],
                },
                priceDefinition: {
                    quantity: product.amount,
                    type: PriceType.QUANTITY,
                    price: this.taxStatus === 'gross'
                        ? product.price[0].gross
                        : product.price[0].net,
                    taxRules: [
                        {
                            taxRate: product.tax.taxRate,
                            percentage: null,
                        },
                    ],
                },
            };
        },

        addCustomItem(customItem: CustomItem): LineItem {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: LineItemType.CUSTOM,
                label: customItem.label,
                description: 'Custom line item',
                quantity: customItem.quantity,
                priceDefinition: {
                    type: PriceType.QUANTITY,
                    price: customItem.price ?? 0.0,
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

        addCredit(credit: CreditItem): LineItem {
            const item = this.createNewOrderLineItem();

            return {
                ...item,
                type: LineItemType.CREDIT,
                label: credit.label,
                description: 'Credit line item',
                priceDefinition: {
                    type: PriceType.ABSOLUTE,
                    price: credit.price ?? 0.0,
                    quantity: 1,
                    taxRules: [],
                },
            };
        },

        createNewOrderLineItem(): LineItem {
            const item = this.orderLineItemRepository.create();

            return {
                ...item,
                versionId: Shopware.Context.api.liveVersionId ?? '',
            } as LineItem;
        },

        isValidItem(item: CustomItem|CreditItem) {
            return item?.label && item?.price;
        },
    },
});
