import BulkEditBaseHandler from './bulk-edit-base.handler';

const types = Shopware.Utils.types;
const { Service, Application } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

/**
 * @class
 * @extends BulkEditBaseHandler
 * @package system-settings
 */
class BulkEditProductHandler extends BulkEditBaseHandler {
    constructor() {
        super();
        this.name = 'bulkEditProductHandler';
        this.calculatePriceService = Application.getContainer('factory').apiService.getByName('calculate-price');
        this.entityName = 'product';
        this.entityIds = [];
        this.products = {};
    }

    async bulkEdit(entityIds, payload) {
        this.entityIds = entityIds;
        const taxId = payload.find(change => change.field === 'taxId')?.value;
        const price = payload.find(change => change.field === 'price')?.value;
        const purchasePrices = payload.find(change => change.field === 'purchasePrices')?.value;
        let updatedPricePayload = [];

        if (taxId || price || purchasePrices) {
            await this.getProducts();
        }

        if (this.shouldRecalculateTax(taxId, price, purchasePrices)) {
            updatedPricePayload = await this.recalculatePrices(taxId, price, purchasePrices);

            payload = payload.filter(change => change.field !== 'taxId');
        }

        if (price || purchasePrices) {
            updatedPricePayload = this.updatePriceDirectly(price, purchasePrices, updatedPricePayload);

            payload = payload.filter(change => change.field !== 'price' && change.field !== 'purchasePrices');
        }

        const syncPayload = await this.buildBulkSyncPayload(payload);

        if (updatedPricePayload.length) {
            if (!types.isEmpty(syncPayload) && 'upsert-product' in syncPayload) {
                syncPayload['upsert-product'].payload = this.mapProductPricesToSyncPayload(
                    syncPayload['upsert-product'].payload,
                    updatedPricePayload,
                );
            } else {
                syncPayload['upsert-product'] = {
                    action: 'upsert',
                    entity: 'product',
                    payload: updatedPricePayload,
                };
            }
        }

        if (types.isEmpty(syncPayload)) {
            return Promise.resolve({ data: [] });
        }

        return this.syncService.sync(syncPayload, {}, {
            'single-operation': 1,
            'sw-language-id': Shopware.Context.api.languageId,
        });
    }

    shouldRecalculateTax(taxId, price, purchasePrices) {
        return taxId && this.isNullPrice(price, purchasePrices);
    }

    isNullPrice(price, purchasePrices) {
        return !price || !price[0].listPrice || !price[0].regulationPrice || !purchasePrices;
    }

    mapProductPricesToSyncPayload(syncPayload, updatePricePayload) {
        const mappedPayload = [];
        syncPayload.forEach(payload => {
            const pricePayload = updatePricePayload.find(productPrice => productPrice.id === payload.id);

            if (pricePayload) {
                payload = { ...payload, ...pricePayload };
                updatePricePayload = updatePricePayload.filter(productPrice => productPrice.id !== payload.id);
            }

            mappedPayload.push(payload);
        });

        return mappedPayload.concat(updatePricePayload);
    }

    getProducts() {
        const productRepository = Service('repositoryFactory').create('product');

        const criteria = new Criteria(1, 25);
        criteria.setIds(this.entityIds);

        return productRepository.search(criteria, Shopware.Context.api).then((products) => {
            this.products = products;
        });
    }

    async recalculatePrices(taxId, inputPrice, inputPurchasePrices) {
        const updatePriceTax = {};
        const updateListPriceTax = {};
        const updateRegulationPriceTax = {};
        const updatePurchasePriceTax = {};

        const products = this.products.filter(product => product.taxId !== taxId);

        products.forEach((product) => {
            if (!inputPrice) {
                const productPrice = product.price?.filter(price => price.linked)?.map(
                    price => this.getRecalculatePrice(price),
                );

                if (!types.isEmpty(productPrice)) {
                    updatePriceTax[product.id] = productPrice;
                }
            }

            if (!inputPrice || !inputPrice[0].listPrice) {
                const productListPrice = product.price?.filter(price => price.listPrice?.linked)?.map(
                    price => this.getRecalculatePrice(price.listPrice),
                );

                if (!types.isEmpty(productListPrice)) {
                    updateListPriceTax[product.id] = productListPrice;
                }
            }

            if (!inputPrice || !inputPrice[0].regulationPrice) {
                const productRegulationPrice = product.price?.filter(price => price.regulationPrice?.linked)?.map(
                    price => this.getRecalculatePrice(price.regulationPrice),
                );

                if (!types.isEmpty(productRegulationPrice)) {
                    updateRegulationPriceTax[product.id] = productRegulationPrice;
                }
            }

            if (!inputPurchasePrices) {
                const productPurchasePrice = product.purchasePrices?.filter(price => price.linked)?.map(
                    price => this.getRecalculatePrice(price),
                );

                if (!types.isEmpty(productPurchasePrice)) {
                    updatePurchasePriceTax[product.id] = productPurchasePrice;
                }
            }
        });

        const results = await Promise.all([
            this.calculatePrices(taxId, updatePriceTax),
            this.calculatePrices(taxId, updateListPriceTax),
            this.calculatePrices(taxId, updateRegulationPriceTax),
            this.calculatePrices(taxId, updatePurchasePriceTax),
        ]);

        const calculatedPrices = results[0];
        const calculatedListPrices = results[1];
        const calculatedRegulationPrices = results[2];
        const calculatedPurchasePrices = results[3];
        const reformatted = [];

        products.forEach((product) => {
            const calculatedPrice = calculatedPrices[product.id] ?? [];
            const calculatedListPrice = calculatedListPrices[product.id] ?? [];
            const calculatedRegulationPrice = calculatedRegulationPrices[product.id] ?? [];
            const calculatedPurchasePrice = calculatedPurchasePrices[product.id] ?? [];
            const currentPrice = {
                id: product.id,
                taxId: taxId,
            };

            if (product.price) {
                currentPrice.price = this.getCalculatedPrices(
                    product.price,
                    calculatedPrice,
                    calculatedListPrice,
                    calculatedRegulationPrice,
                );
            }

            if (product.purchasePrices) {
                currentPrice.purchasePrices = this.getCalculatedPrices(product.purchasePrices, calculatedPurchasePrice);
            }

            reformatted.push(currentPrice);
        });

        return reformatted;
    }

    getRecalculatePrice(price) {
        return {
            price: price.gross,
            currencyId: price.currencyId,
        };
    }

    async calculatePrices(taxId, prices) {
        if (prices === null || Object.keys(prices).length === 0) {
            return {};
        }

        return this.calculatePriceService.calculatePrices(taxId, prices);
    }

    getCalculatedPrices(dbPrices, calculatedPrices, calculatedListPrices = [], calculatedRegulationPrices = []) {
        const price = [];
        dbPrices.forEach(dbPrice => {
            const { currencyId, listPrice, regulationPrice } = dbPrice;
            if (dbPrice.linked && calculatedPrices[currencyId]) {
                dbPrice.net = dbPrice.gross - this.getTax(calculatedPrices[currencyId].calculatedTaxes);
            }

            if (listPrice?.linked && calculatedListPrices[currencyId]) {
                dbPrice.listPrice.net = listPrice.gross - this.getTax(calculatedListPrices[currencyId].calculatedTaxes);
            }

            if (regulationPrice?.linked && calculatedRegulationPrices[currencyId]) {
                const regulationPriceTaxes = calculatedRegulationPrices[currencyId].calculatedTaxes;
                dbPrice.regulationPrice.net = regulationPrice.gross - this.getTax(regulationPriceTaxes);
            }

            price.push(dbPrice);
        });

        return price;
    }

    getTax(calculatedTaxes) {
        let tax = 0;

        calculatedTaxes.forEach((item) => {
            tax += item.tax;
        });

        return tax;
    }

    updatePriceDirectly(inputPrice, inputPurchasePrices, calculatedProductPrices) {
        const payload = [];
        this.products.forEach((product) => {
            const calculatedPrice = calculatedProductPrices.find(productPrice => productPrice.id === product.id);
            const currentData = calculatedPrice ?? { id: product.id };

            if (inputPrice) {
                const originalPrice = calculatedPrice?.price ?? product.price;
                currentData.price = this.updatePrice(inputPrice[0], originalPrice);
            }

            if (inputPurchasePrices) {
                currentData.purchasePrices = this.updatePrice(inputPurchasePrices[0], product.purchasePrices);
            }

            if (calculatedPrice) {
                calculatedProductPrices = calculatedProductPrices.filter(productPrice => productPrice.id !== product.id);
            }

            payload.push(currentData);
        });

        return payload.concat(calculatedProductPrices);
    }

    updatePrice(inputPrice, dbPrices) {
        const currencyId = inputPrice.currencyId;
        let productPrices = [];
        const dbPrice = dbPrices?.find(productPrice => productPrice.currencyId === currencyId) ?? null;
        const currentPrice = this.getPrice(inputPrice, dbPrice);

        if (dbPrices) {
            productPrices = dbPrices.filter(productPrice => productPrice.currencyId !== currencyId);
        }

        productPrices.push(currentPrice);

        return productPrices;
    }

    getPrice(inputPrice, dbPrice) {
        const dbListPrice = dbPrice?.listPrice;
        const dbRegulationPrice = dbPrice?.regulationPrice;
        let currentPrice = cloneDeep(inputPrice);
        currentPrice = this.formatPrice(currentPrice, dbPrice);

        // Set listPrice as the input value if exist, otherwise, set listPrice as the old value in the DB
        if (currentPrice.listPrice) {
            currentPrice.listPrice = this.formatPrice(currentPrice.listPrice, dbListPrice);
        } else if (dbListPrice) {
            currentPrice.listPrice = dbListPrice;
        }

        // Set regulationPrice as the input value if exist, otherwise, set regulationPrice as the old value in the DB
        if (currentPrice.regulationPrice) {
            currentPrice.regulationPrice = this.formatPrice(currentPrice.regulationPrice, dbRegulationPrice);
        } else if (dbRegulationPrice) {
            currentPrice.regulationPrice = dbRegulationPrice;
        }

        return currentPrice;
    }

    formatPrice(price, dbPrice) {
        if (price.gross === null) {
            price.linked = false;
            price.gross = dbPrice?.gross ?? 0;
        }

        if (price.net === null) {
            price.linked = false;
            price.net = dbPrice?.net ?? 0;
        }

        return price;
    }
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default BulkEditProductHandler;
