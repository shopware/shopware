import FilterFactory from 'src/core/data-new/filter-factory.data';
import mockEntitySchema from 'src/../test/_mocks_/entity-schema.json';

const EntityDefinitionFactory = require('src/core/factory/entity-definition.factory').default;

beforeEach(async () => {
    Shopware.EntityDefinition = EntityDefinitionFactory;
    Shopware.EntityDefinition.add('product', mockEntitySchema.product);
    Shopware.EntityDefinition.add('product_manufacturer', mockEntitySchema.product_manufacturer);
    Shopware.EntityDefinition.add('tag', mockEntitySchema.tag);
    Shopware.EntityDefinition.add('product_price', mockEntitySchema.product_price);
});

describe('filter-factory.data.js', () => {
    it('should return the corresponding filter for the passed fields', async () => {
        const filterFactory = new FilterFactory();
        const productFilter = filterFactory.create('product', {
            'tag-filter': {
                label: 'Product tag',
                property: 'tags'
            },
            'newly-added-filter': {
                label: 'Newly Added',
                property: 'tags'
            }
        });

        expect(productFilter[0].label).toBe('Product tag');
        expect(productFilter[1]).toBeTruthy();
    });

    it('should return the corresponding filter based on entity schema', async () => {
        const filterFactory = new FilterFactory();
        const productFilter = filterFactory.create('product', {
            'tag-filter': {
                label: 'Product tag',
                property: 'tags'
            },
            'name-filter': {
                schema: 'name',
                property: 'name',
                label: 'Product name'
            },
            'available-stock-filter': {
                property: 'availableStock'
            },
            'release-date-filter': {
                property: 'releaseDate'
            },
            'product-price-filter': {
                property: 'price'
            }
        });

        expect(productFilter[0].type).toBe('multi-select-filter');
        expect(productFilter[1].type).toBe('string-filter');
        expect(productFilter[2].type).toBe('number-filter');
        expect(productFilter[3].type).toBe('date-filter');
        expect(productFilter[4].type).toBe('price-filter');
    });

    it('should return correct entity properties for nested filter', async () => {
        const filterFactory = new FilterFactory();
        const productFilter = filterFactory.create('product', {
            'prices-product-filter': {
                label: 'Line items product',
                property: 'prices.productId'
            }
        });

        expect(productFilter[0].type).toBe('multi-select-filter');
    });

    it('should return correct association for foreign key filter', async () => {
        const filterFactory = new FilterFactory();
        const productFilter = filterFactory.create('product', {
            'manufacturer-id-filter': {
                property: 'manufacturerId'
            }
        });

        expect(productFilter[0].type).toBe('multi-select-filter');
    });
});
