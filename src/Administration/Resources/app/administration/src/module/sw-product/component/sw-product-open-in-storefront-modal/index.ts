import type { PropType } from 'vue';
import type RepositoryType from 'src/core/data/repository.data';
import useNotification from 'src/app/composables/use-notification';
import type { ProductStorefrontUrls } from '../../service/product-storefront-url.api.service';
import template from './sw-product-open-in-storefront-modal.html.twig';
import './sw-product-open-in-storefront-modal.scss';

const { Criteria } = Shopware.Data;

const MAINTENANCE_MODE_ERROR_CODE = 'FRAMEWORK__API_SALES_CHANNEL_MAINTENANCE_MODE';

type StockReason = 'inStock' | 'inStockPossibly';

type FailingReason =
    | 'salesChannelActive'
    | 'hasDomain'
    | 'maintenanceAllowlist'
    | 'productActive'
    | StockReason
    | 'storefrontShowsProduct';

type ApiError = {
    response?: {
        data?: {
            errors?: { code?: string }[];
        };
    };
};

type LoadedSalesChannel = {
    productUrls: ProductStorefrontUrls | null;
    stockReason: StockReason | null;
    languageNames: Record<string, string>;
    isBlockedByMaintenance?: boolean;
};

type SalesChannelResult = {
    maintenance: boolean;
    failingReasons: FailingReason[];
    domains: ProductStorefrontUrls['domains'];
    languageNames: Record<string, string>;
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
        productRepository(): RepositoryType<'product'> {
            return this.repositoryFactory.create('product');
        },

        languageRepository(): RepositoryType<'language'> {
            return this.repositoryFactory.create('language');
        },

        modalSubtitle(): string {
            const variations = (this.product.variation ?? []) as { group: string; option: string }[];
            const variation = variations.map(({ group, option }) => `${group}: ${option}`).join(' | ');

            return variation ? `${this.productName} (${variation})` : this.productName;
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

        persistedProduct(): EntitySchema.Entities['product'] {
            return this.product.getOrigin();
        },

        persistedParentProduct(): EntitySchema.Entities['product'] | null {
            return this.parentProduct?.id ? this.parentProduct.getOrigin() : null;
        },

        isProductActive(): boolean {
            return this.persistedProduct.active ?? this.persistedParentProduct?.active ?? false;
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
                    languageNames: {},
                };
            }

            if (!this.loadedSalesChannel) {
                return null;
            }

            const { productUrls, stockReason, languageNames, isBlockedByMaintenance } = this.loadedSalesChannel;

            return {
                maintenance: !!salesChannel.maintenance,
                failingReasons: isBlockedByMaintenance
                    ? ['maintenanceAllowlist']
                    : this.getFailingReasons(productUrls, stockReason),
                domains: productUrls?.reachable ? productUrls.domains : [],
                languageNames,
            };
        },

        invalidDomainUrls(): string[] {
            return (this.result?.domains ?? []).filter(({ productUrl }) => !productUrl).map(({ url }) => url);
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
                const [stockReason, languageNames] = await Promise.all([
                    productUrls && !productUrls.reachable ? this.loadStockReason(salesChannel.id) : null,
                    productUrls?.reachable ? this.loadLanguageNames(productUrls.domains) : {},
                ]);

                if (!isCancelled()) {
                    this.loadedSalesChannel = { productUrls, stockReason, languageNames };
                }
            } catch (error) {
                this.onLoadError(error, isCancelled);
            } finally {
                if (!isCancelled()) {
                    this.isLoading = false;
                }
            }
        },

        onLoadError(error: unknown, isCancelled: () => boolean): void {
            if ((error as ApiError).response?.data?.errors?.[0]?.code !== MAINTENANCE_MODE_ERROR_CODE) {
                useNotification().createNotificationError({
                    message: this.$t('sw-product.openInStorefrontModal.notificationOpenInStorefrontError'),
                });

                return;
            }

            if (!isCancelled()) {
                this.loadedSalesChannel = {
                    productUrls: null,
                    stockReason: null,
                    languageNames: {},
                    isBlockedByMaintenance: true,
                };
            }
        },

        async loadLanguageNames(domains: ProductStorefrontUrls['domains']): Promise<Record<string, string>> {
            const criteria = new Criteria(1, domains.length);
            criteria.setIds([...new Set(domains.map(({ languageId }) => languageId))]);

            try {
                const languages = await this.languageRepository.search(criteria);

                return Object.fromEntries(languages.map((language) => [language.id, language.name]));
            } catch {
                return {};
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
            if ((this.persistedProduct.childCount ?? 0) > 0) {
                const criteria = new Criteria(1, 1);
                criteria.setTotalCountMode(0);
                criteria.addFilter(Criteria.equals('parentId', this.product.id));
                criteria.addFilter(
                    Criteria.not('AND', [
                        Criteria.equals('isCloseout', true),
                        Criteria.equals('available', false),
                    ]),
                );

                const availableVariants = await this.productRepository.searchIds(criteria, {
                    ...Shopware.Context.api,
                    inheritance: true,
                });

                return availableVariants.data.length === 0;
            }

            const isCloseout = this.persistedProduct.isCloseout ?? this.persistedParentProduct?.isCloseout;

            return !!isCloseout && this.persistedProduct.available === false;
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
