/*
 * @sw-package inventory
 */

import type EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';
import type { ContextStore } from '../../../../app/store/context.store';

type LoadingProperties =
    | 'init'
    | 'product'
    | 'parentProduct'
    | 'manufacturers'
    | 'currencies'
    | 'taxes'
    | 'customFieldSets'
    | 'media'
    | 'rules'
    | 'variants'
    | 'defaultFeatureSet'
    | 'advancedMode';

const swProductDetail = Shopware.Store.register({
    id: 'swProductDetail',

    state() {
        return {
            product: {} as EntitySchema.product & { isNew: () => boolean },
            parentProduct: {} as EntitySchema.product,
            currencies: [] as EntitySchema.currency[],
            apiContext: {} as ContextStore['api'],
            taxes: [] as EntitySchema.tax[],
            variants: [],
            customFieldSets: [] as { id: string }[],
            defaultFeatureSet: {} as EntitySchema.product_feature_set,
            loading: {
                init: false,
                product: false,
                parentProduct: false,
                manufacturers: false,
                currencies: false,
                taxes: false,
                customFieldSets: false,
                media: false,
                rules: false,
                variants: false,
                defaultFeatureSet: false,
                advancedMode: false,
            },
            localMode: false,
            advancedModeSetting: {} as { value?: { advancedMode: { enabled: boolean } } },
            modeSettings: [
                'general_information',
                'prices',
                'deliverability',
                'visibility_structure',
                'media',
                'labelling',
                'measures_packaging',
                'properties',
                'essential_characteristics',
                'custom_fields',
            ],
            /* Product "types" provided by the split button for creating a new product through a router parameter */
            creationStates: [] as string[],
        };
    },

    getters: {
        isLoading: (state): boolean => {
            return Object.values(state.loading).some((loadState) => loadState);
        },

        defaultCurrency(state): EntitySchema.currency | { id: undefined } {
            if (!state.currencies || !state.currencies.length) {
                return { id: undefined };
            }

            const defaultCurrency = state.currencies.find((currency) => currency.isSystemDefault);

            return defaultCurrency || { id: undefined };
        },

        defaultPrice(state): object {
            let productPrice: [] = state.product.price as [];

            // check if price exist
            if (!productPrice) {
                // if parent price does not exist
                if (!state.parentProduct.price) {
                    return {};
                }

                productPrice = state.parentProduct.price as [];
            }

            // get default price bases on currency
            return (
                productPrice.find((price: { currencyId: 'string' }) => {
                    return price.currencyId === this.defaultCurrency.id;
                }) ?? {}
            );
        },

        getDefaultFeatureSet(state): EntitySchema.product_feature_set | object {
            if (!state.defaultFeatureSet) {
                return {};
            }

            return state.defaultFeatureSet;
        },

        productTaxRate(state): EntitySchema.tax | object {
            if (!state.taxes) {
                return {};
            }

            return (
                state.taxes.find((tax) => {
                    if (!state.product.taxId) {
                        if (!state.parentProduct.taxId) {
                            return {};
                        }

                        return tax.id === state.parentProduct.taxId;
                    }

                    return tax.id === state.product.taxId;
                }) ?? {}
            );
        },

        isChild(state): boolean {
            return !!state.product?.parentId;
        },

        showModeSetting(state): boolean {
            return !!state.product?.parentId || this.advanceModeEnabled;
        },

        advanceModeEnabled(state): boolean {
            return !!state.advancedModeSetting.value?.advancedMode.enabled;
        },

        productStates(state): string[] {
            if (state.product.isNew?.() && state.creationStates) {
                return state.creationStates;
            }

            if (state.product.states) {
                return state.product.states as string[];
            }

            return [];
        },
    },

    actions: {
        showProductCard(key: string) {
            if (this.product?.parentId) {
                return true;
            }

            const cardKeys = [
                'essential_characteristics',
                'custom_fields',
                'labelling',
            ];

            if (cardKeys.includes(key) && !this.showModeSetting) {
                return false;
            }

            return this.modeSettings?.includes(key);
        },

        setCustomFields(fieldSet: { id: string }) {
            this.customFieldSets = this.customFieldSets.map((set) => {
                if (set.id === fieldSet.id) {
                    return fieldSet;
                }
                return set;
            });
        },

        setLoading(value: [LoadingProperties, boolean]) {
            const name = value[0];
            const data = value[1];

            // check for using from JS
            if (typeof data !== 'boolean') {
                return false;
            }

            if (this.loading[name] !== undefined) {
                this.loading[name] = data;
                return true;
            }
            return false;
        },

        setAssignedProductsFromCrossSelling({
            id,
            collection,
        }: {
            id: string;
            collection: EntityCollection<'product_cross_selling_assigned_products'>;
        }) {
            const entity = this.product.crossSellings?.get(id);
            if (!entity) return;
            entity.assignedProducts = collection;
        },

        setTaxes(newTaxes: EntitySchema.tax[]) {
            this.taxes = newTaxes;

            if (this.product && this.product.taxId === null && !this.parentProduct.id) {
                this.product.taxId = this.taxes[0]?.id;
            }
        },

        setDefaultFeatureSet(newDefaultFeatureSet: EntitySchema.product_feature_set) {
            this.defaultFeatureSet = newDefaultFeatureSet;
        },
    },
});

/**
 * @private
 */
export default swProductDetail;

/**
 * @private
 */
export type SwProductDetailStore = ReturnType<typeof swProductDetail>;
