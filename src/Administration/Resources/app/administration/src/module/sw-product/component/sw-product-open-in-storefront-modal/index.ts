import type { PropType } from 'vue';
import useNotification from 'src/app/composables/use-notification';
import type { ProductStorefrontUrls } from '../../service/product-storefront-url.api.service';
import template from './sw-product-open-in-storefront-modal.html.twig';
import './sw-product-open-in-storefront-modal.scss';

const { Criteria } = Shopware.Data;

type StockReason = 'inStock' | 'inStockPossibly';

type FailingReason = 'salesChannelActive' | 'hasDomain' | 'productActive' | StockReason | 'storefrontShowsProduct';

type LoadedSalesChannel = {
    productUrls: ProductStorefrontUrls | null;
    stockReason: StockReason | null;
};

type SalesChannelResult = {
    maintenance: boolean;
    failingReasons: FailingReason[];
    domains: ProductStorefrontUrls['domains'];
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
        'productStorefrontUrlApiService',
    ],

    emits: ['modal-close'],

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
            return this.$t('sw-product.openInStorefrontModal.title', {
                name: this.productName,
                productNumber: this.product.productNumber,
            });
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

        modalDescription(): string {
            return this.$t('sw-product.openInStorefrontModal.description', {
                name: this.productName,
                productNumber: this.product.productNumber,
            });
        },

        selectedSalesChannelName(): string {
            return this.salesChannelOptions.find(({ value }) => value === this.selectedSalesChannelId)?.label ?? '';
        },

        result(): SalesChannelResult | null {
            const salesChannel = this.selectedSalesChannel;
            if (!salesChannel) {
                return null;
            }

            if (!salesChannel.active) {
                return {
                    maintenance: !!salesChannel.maintenance,
                    failingReasons: ['salesChannelActive'],
                    domains: [],
                };
            }

            if (!this.loadedSalesChannel) {
                return null;
            }

            const { productUrls, stockReason } = this.loadedSalesChannel;

            return {
                maintenance: !!salesChannel.maintenance,
                failingReasons: this.getFailingReasons(productUrls, stockReason),
                domains: productUrls?.reachable ? productUrls.domains : [],
            };
        },
    },

    watch: {
        selectedSalesChannelId(
            _value: string | null,
            _oldValue: string | null,
            onCleanup: (cleanup: () => void) => void,
        ): void {
            let isCancelled = false;
            onCleanup(() => {
                isCancelled = true;
            });

            void this.loadSelectedSalesChannel(() => isCancelled);
        },
    },

    created(): void {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            if (this.salesChannels.length === 1) {
                this.selectedSalesChannelId = this.salesChannels[0].id;
            }
        },

        async loadSelectedSalesChannel(isCancelled: () => boolean): Promise<void> {
            this.loadedSalesChannel = null;

            const salesChannel = this.selectedSalesChannel;
            if (!salesChannel?.active) {
                this.isLoading = false;
                return;
            }

            this.isLoading = true;

            try {
                const productUrls = await this.productStorefrontUrlApiService.fetchProductUrls(
                    this.product.id,
                    salesChannel.id,
                );
                const stockReason =
                    productUrls && !productUrls.reachable ? await this.loadStockReason(salesChannel.id) : null;

                if (!isCancelled()) {
                    this.loadedSalesChannel = { productUrls, stockReason };
                }
            } catch {
                useNotification().createNotificationError({
                    message: this.$t('sw-product.openInStorefrontModal.notificationOpenInStorefrontError'),
                });
            } finally {
                if (!isCancelled()) {
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
                const values = await this.systemConfigApiService.getValues('core.listing', salesChannelId, {
                    inherit: true,
                });

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

        getFailingReasons(productUrls: ProductStorefrontUrls | null, stockReason: StockReason | null): FailingReason[] {
            if (!productUrls) {
                return ['hasDomain'];
            }

            if (productUrls.reachable) {
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

        onModalRootChange(isOpen: boolean): void {
            if (!isOpen) {
                this.$emit('modal-close');
            }
        },
    },
});
