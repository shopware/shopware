import type { PropType } from 'vue';
import type { ProductStorefrontSalesChannel } from '../../service/product-storefront-url.api.service';
import template from './sw-product-open-in-storefront-modal.html.twig';
import './sw-product-open-in-storefront-modal.scss';

const { Criteria } = Shopware.Data;

type StockReason = 'inStock' | 'inStockPossibly';

type FailingReason = 'salesChannelActive' | 'hasDomain' | 'productActive' | StockReason | 'storefrontShowsProduct';

type LoadedSalesChannel = {
    salesChannelId: string;
    storefrontSalesChannel: ProductStorefrontSalesChannel | null;
    stockReason: StockReason | null;
};

type SalesChannelResult = {
    reachable: boolean;
    maintenance: boolean;
    failingReasons: FailingReason[];
    domains: ProductStorefrontSalesChannel['domains'];
};

/**
 * @private
 * @sw-package inventory
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'repositoryFactory',
        'systemConfigApiService',
    ],

    emits: ['modal-close'],

    mixins: [Shopware.Mixin.getByName('notification')],

    props: {
        product: {
            type: Object as PropType<Entity<'product'>>,
            required: true,
        },

        parentProduct: {
            type: Object as PropType<Entity<'product'> | null>,
            required: false,
            default: null,
        },

        productName: {
            type: String,
            required: true,
        },

        salesChannels: {
            type: Array as PropType<Entity<'sales_channel'>[]>,
            required: true,
        },

        hasUnsavedChanges: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        selectedSalesChannelId: string | null;
        loadedSalesChannel: LoadedSalesChannel | null;
        isLoading: boolean;
    } {
        return {
            selectedSalesChannelId: null,
            loadedSalesChannel: null,
            isLoading: false,
        };
    },

    computed: {
        modalTitle(): string {
            return this.$t('sw-product.openInStorefrontModal.title', { name: this.productName });
        },

        salesChannelOptions(): { value: string; label: string }[] {
            return this.salesChannels
                .map((salesChannel) => ({
                    value: salesChannel.id,
                    label: salesChannel.translated?.name ?? salesChannel.name ?? '',
                }))
                .sort((a, b) => a.label.localeCompare(b.label));
        },

        selectedSalesChannel(): Entity<'sales_channel'> | null {
            return this.salesChannels.find(({ id }) => id === this.selectedSalesChannelId) ?? null;
        },

        isProductActive(): boolean {
            return this.product.active ?? this.parentProduct?.active ?? false;
        },

        reachabilityLabel(): string {
            return this.result?.reachable
                ? this.$t('sw-product.openInStorefrontModal.labelReachable')
                : this.$t('sw-product.openInStorefrontModal.labelNotReachable');
        },

        result(): SalesChannelResult | null {
            const salesChannel = this.selectedSalesChannel;
            if (!salesChannel) {
                return null;
            }

            if (!salesChannel.active) {
                return {
                    reachable: false,
                    maintenance: !!salesChannel.maintenance,
                    failingReasons: ['salesChannelActive'],
                    domains: [],
                };
            }

            if (this.loadedSalesChannel?.salesChannelId !== salesChannel.id) {
                return null;
            }

            const { storefrontSalesChannel, stockReason } = this.loadedSalesChannel;

            return {
                reachable: storefrontSalesChannel?.reachable ?? false,
                maintenance: !!salesChannel.maintenance,
                failingReasons: this.getFailingReasons(storefrontSalesChannel, stockReason),
                domains: storefrontSalesChannel?.domains ?? [],
            };
        },
    },

    watch: {
        selectedSalesChannelId(): void {
            void this.loadSelectedSalesChannel();
        },
    },

    methods: {
        async loadSelectedSalesChannel(): Promise<void> {
            const salesChannel = this.selectedSalesChannel;
            if (!salesChannel?.active) {
                return;
            }

            this.isLoading = true;

            try {
                const storefrontSalesChannel = await Shopware.Service('productStorefrontUrlApiService').fetchSalesChannel(
                    this.product.id,
                    salesChannel.id,
                );
                const stockReason =
                    storefrontSalesChannel && !storefrontSalesChannel.reachable
                        ? await this.loadStockReason(salesChannel.id)
                        : null;

                if (this.selectedSalesChannelId === salesChannel.id) {
                    this.loadedSalesChannel = {
                        salesChannelId: salesChannel.id,
                        storefrontSalesChannel,
                        stockReason,
                    };
                }
            } catch {
                this.createNotificationError({
                    message: this.$t('sw-product.detail.notificationOpenInStorefrontError'),
                });
            } finally {
                if (this.selectedSalesChannelId === salesChannel.id) {
                    this.isLoading = false;
                }
            }
        },

        async loadStockReason(salesChannelId: string): Promise<StockReason | null> {
            if (!(await this.isSoldOutClearanceSale())) {
                return null;
            }

            if (!this.acl.can('system_config:read')) {
                return 'inStockPossibly';
            }

            try {
                const values = (await this.systemConfigApiService.getValues('core.listing', salesChannelId, {
                    inherit: true,
                })) as Record<string, unknown>;

                return values['core.listing.hideCloseoutProductsWhenOutOfStock'] ? 'inStock' : null;
            } catch {
                return 'inStockPossibly';
            }
        },

        async isSoldOutClearanceSale(): Promise<boolean> {
            if ((this.product.childCount ?? 0) > 0) {
                const criteria = new Criteria(1, 1);
                criteria.addFilter(Criteria.equals('parentId', this.product.id));
                criteria.addFilter(
                    Criteria.not('AND', [
                        Criteria.equals('isCloseout', true),
                        Criteria.equals('available', false),
                    ]),
                );

                const availableVariants = await this.repositoryFactory.create('product').searchIds(criteria, {
                    ...Shopware.Context.api,
                    inheritance: true,
                });

                return availableVariants.total === 0;
            }

            const isCloseout = this.product.isCloseout ?? this.parentProduct?.isCloseout;

            return !!isCloseout && this.product.available === false;
        },

        getFailingReasons(
            storefrontSalesChannel: ProductStorefrontSalesChannel | null,
            stockReason: StockReason | null,
        ): FailingReason[] {
            if (!storefrontSalesChannel) {
                return ['hasDomain'];
            }

            if (storefrontSalesChannel.reachable) {
                return [];
            }

            const reasons: FailingReason[] = [];
            if (!this.isProductActive) {
                reasons.push('productActive');
            }

            if (stockReason) {
                reasons.push(stockReason);
            }

            return reasons.length > 0 ? reasons : ['storefrontShowsProduct'];
        },

        onClose(): void {
            this.$emit('modal-close');
        },
    },
});
