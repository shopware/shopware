
const { Criteria, RepositoryLoader } = Shopware.Data;

export default class PricingService {

    constructor(defaultCurrency) {
        this.defaultCurrency = defaultCurrency;
    }

    validate(rows) {
        const prices = {};
        const discounts = {};
        const grouped = {};
        const errors = {};

        rows.forEach((row, index) => {
            const hash = this.hash(row);

            if (this.isDiscount(row)) {
                discounts[hash] = index;
            }
            if (this.isPrice(row)) {
                prices[hash] = index;
            }
            if (grouped[hash] === undefined) {
                grouped[hash] = [];
            }

            grouped[hash].push({row, index});
        });

        Object.keys(grouped).forEach((hash) => {
            const group = grouped[hash];

            // sort by quantity start, null values first
            group.sort((a, b) => {
                if (a.quantityStart === null && b.quantityStart !== null) return -1;
                if (a.quantityStart !== null && b.quantityStart === null) return 1;
                return a.quantityStart - b.quantityStart;
            });

            group.forEach((item, groupIndex) => {
                const rowErrors = [];
                const row = item.row;
                const index = item.index;
                const before = group[groupIndex - 1]?.row ?? null;
                const next = group[groupIndex + 1]?.row ?? null;

                if (!this.isPrice(row) && !this.isDiscount(row)) {
                    rowErrors.push('<li class="pricing-error">Price or discount must be defined</li>');
                }

                if (this.isPrice(row) && row.quantityStart === null) {
                    rowErrors.push('<li class="pricing-error">Quantity start must be defined for price rows</li>');
                }

                if (this.isDiscount(row) && prices[hash] !== undefined) {
                    rowErrors.push('<li class="pricing-error">Price and discount are not allowed at the same time. Price is already defined in line ' + (prices[hash] + 1) + '</li>');
                }

                if (this.isPrice(row) && discounts[hash] !== undefined) {
                    rowErrors.push('<li class="pricing-error">Price and discount are not allowed at the same time. Discount is already defined in line ' + (discounts[hash] + 1) + '</li>');
                }

                if (this.isDiscount(row) && this.isPrice(row)) {
                    rowErrors.push('<li class="pricing-error">Discount and price are not allowed at the same time</li>');
                }

                if (row.customerGroupId === null && row.salesChannelId === null && row.countryId === null && this.isDiscount(row)) {
                    rowErrors.push('<li class="pricing-error">Default pricing is not allowed for discounts</li>');
                }

                if (row.salesChannelId !== null && row.customerGroupId === null) {
                    rowErrors.push('<li class="pricing-error">Customer group is required if sales channel is defined</li>');
                }

                if (row.countryId !== null && row.customerGroupId === null) {
                    rowErrors.push('<li class="pricing-error">Customer group is required if country is defined</li>');
                }

                if (row.countryId !== null && row.salesChannelId === null) {
                    rowErrors.push('<li class="pricing-error">Sales channel is required if country is defined</li>');
                }

                if (row.quantityStart >= row.quantityEnd && row.quantityEnd !== null) {
                    rowErrors.push('<li class="pricing-error">Quantity end must be greater than quantity start</li>');
                }

                if (before && row.quantityStart !== before.quantityEnd + 1) {
                    rowErrors.push('<li class="pricing-error">Quantity start of the row should be equal to the previous quantity end + 1</li>');
                }

                if (!next && row.quantityEnd !== null) {
                    rowErrors.push('<li class="pricing-error">Quantity end must be null for the last entry</li>');
                }

                errors[index] = rowErrors;
            });
        });

        return errors;
    }

    sort(prices) {
        prices.sort((a, b) => {
            // Sort by customerGroupId
            if (a.customerGroupId === null && b.customerGroupId !== null) return -1;
            if (a.customerGroupId !== null && b.customerGroupId === null) return 1;
            if (a.customerGroupId !== b.customerGroupId) return a.customerGroupId.localeCompare(b.customerGroupId);

            // Sort by salesChannelId
            if (a.salesChannelId === null && b.salesChannelId !== null) return -1;
            if (a.salesChannelId !== null && b.salesChannelId === null) return 1;
            if (a.salesChannelId !== b.salesChannelId) return a.salesChannelId.localeCompare(b.salesChannelId);

            // Sort by countryId
            if (a.countryId === null && b.countryId !== null) return -1;
            if (a.countryId !== null && b.countryId === null) return 1;
            if (a.countryId !== b.countryId) return a.countryId.localeCompare(b.countryId);

            // Sort by quantityStart
            if (a.quantityStart === null && b.quantityStart !== null) return -1;
            if (a.quantityStart !== null && b.quantityStart === null) return 1;
            return a.quantityStart - b.quantityStart;
        });

        return prices;
    }

    save(productId, prices) {
        const payload = [];
        const records = [];

        prices.forEach((price) => {
            records.push({
                productId: productId,
                customerGroupId: price.customerGroupId,
                salesChannelId: price.salesChannelId,
                countryId: price.countryId,
                quantityStart: price.quantityStart,
                quantityEnd: price.quantityEnd,
                price: price.price,
                discount: price.discount
            })
        });

        payload.push({
            productId: productId,
            pricing: records
        });

        const client = Shopware.Application.getContainer('init').httpClient;

        return client.post('/_action/sync/stock-pricing', payload, { headers: this.getHeaders() });
    }

    load(productId) {
        const criteria = new Criteria();
        criteria.addFilter(Criteria.equals('productId', productId));
        criteria.addSorting(Criteria.sort('customerGroupId', 'ASC'));
        criteria.addSorting(Criteria.sort('salesChannelId', 'ASC'));
        criteria.addSorting(Criteria.sort('countryId', 'ASC'));
        criteria.addSorting(Criteria.sort('quantityStart', 'ASC'));

        const loader = new RepositoryLoader();

        return loader.load(criteria, 'product_pricing');
    }

    getHeaders() {
        return {
            Accept: 'application/json',
            Authorization: `Bearer ${Shopware.Service('loginService').getToken()}`,
            'Content-Type': 'application/json',
        };
    }

    hash(price) {
        if (price === null) {
            return null;
        }
        return JSON.stringify([price.customerGroupId, price.salesChannelId, price.countryId]);
    }

    isDiscount(row) {
        return row.discount !== null && row.discount !== 0;
    }

    isPrice(row) {
        const system = this.systemPrice(row);
        return row.price !== null && system.gross !== 0;
    }

    systemPrice(item) {
        if (item.price === null) {
            return { currencyId: this.defaultCurrency.id, gross: 0, linked: true, net: 0 };
        }
        return item.price.find((price) => price.currencyId === this.defaultCurrency.id);
    }
}
