import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import ApiService from 'src/core/service/api.service';

import { itAsync, xitAsync } from '../../../async-helper';

const Entity = Shopware.Entity;
const State = Shopware.State;
const Application = Shopware.Application;

describe('core/data/EntityStore.js', () => {
    test(
        'should be iterate over the entities and create entity stores for each entity',
        () => {
            const definitions = Entity.getDefinitionRegistry();
            const definitionKeys = [...definitions.keys()];

            const stores = State.getStoreRegistry();
            const storeKeys = [...stores.keys()];

            definitionKeys.forEach((key) => {
                expect(storeKeys.includes(key)).toBe(true);
            });
        }
    );

    test('should initialize an EntityStore using a predefined api service', () => {
        const store = new EntityStore('product', 'productService', EntityProxy);

        expect(typeof store.apiService).toBe('object');
        expect(typeof store.store).toBe('object');
        expect(store.isLoading).toBe(false);
        expect(store.getEntityName()).toBe('product');
    });

    test(
        'should initialized an EntityStore using an instance of an api service',
        () => {
            const initContainer = Application.getContainer('init');
            const serviceContainer = Application.getContainer('service');

            const store = new EntityStore('product', new ApiService(
                initContainer.httpClient,
                serviceContainer.loginService,
                'product'
            ), EntityProxy);

            expect(typeof store.apiService).toBe('object');
            expect(typeof store.store).toBe('object');
            expect(store.isLoading).toBe(false);
            expect(store.getEntityName()).toBe('product');
        }
    );

    test('should create a new entity in the store', () => {
        const store = new EntityStore('product', 'productService', EntityProxy);

        const entity = store.create();

        expect(entity.isLocal).toBe(true);
        expect(entity.isDeleted).toBe(false);
        expect(entity.isLoading).toBe(false);
        expect(entity.id.length).toBe(32);

        const newEntity = store.create(entity.id);
        expect(newEntity.id.length).toBe(32);

        expect(entity.id).toBe(newEntity.id);
    });

    itAsync('should create a new entity in the store and save it to the server', (done) => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.factor = 6844.41;
        entity.symbol = 'Ƀ';
        entity.decimalPrecision = 2;
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then((response) => {
            expect(response.id).toBe(entity.id);
            expect(response.factor).toBe(entity.factor);
            expect(response.symbol).toBe(entity.symbol);
            expect(response.shortName).toBe(entity.shortName);
            expect(response.name).toBe(entity.name);

            entity.delete(true).then(() => {
                done();
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    itAsync('should load an entity from the server', (done) => {
        const serviceContainer = Application.getContainer('service');
        const store = new EntityStore('currency', serviceContainer.currencyService, EntityProxy);

        // Create a new entry
        const entity = new EntityProxy('currency', serviceContainer.currencyService);
        entity.factor = 6844.41;
        entity.decimalPrecision = 2;
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then((response) => {
            store.getByIdAsync(response.id).then((storeEntity) => {
                expect(entity.id).toBe(storeEntity.id);
                expect(storeEntity.symbol).toBe('Ƀ');
                expect(storeEntity.shortName).toBe('BTC');
                expect(storeEntity.name).toBe('Bitcoin');

                storeEntity.delete(true).then(() => {
                    done();
                }).catch((err) => {
                    done(err);
                });
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    itAsync('should get a list by using a page and limit', (done) => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.factor = 6844.41;
        entity.decimalPrecision = 2;
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        const page = 1;
        const limit = 500;

        store.getList({ page, limit }).then((storeResponse) => {
            const totalBeforeSave = storeResponse.total;
            const itemsBeforeSave = storeResponse.items;

            entity.save().then(() => {
                store.getList({ page, limit }).then((response) => {
                    expect(response.total).toBe(totalBeforeSave + 1);

                    if (itemsBeforeSave.length < limit) {
                        expect(response.items.length).toBe(itemsBeforeSave.length + 1);
                    }

                    entity.delete(true).then(() => {
                        done();
                    });
                }).catch((err) => {
                    done(err);
                });
            }).catch((err) => {
                done(err);
            });
        });
    });

    itAsync('should get a list with a specific term', (done) => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.factor = 6844.41;
        entity.symbol = 'Ƀ';
        entity.decimalPrecision = 2;
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then(() => {
            store.getList({
                page: 1,
                limit: 1,
                term: 'Bitcoin'
            }).then((response) => {
                expect(response.items.length).toBe(1);

                entity.delete(true).then(() => {
                    done();
                });
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    itAsync('should get a list and load associations', (done) => {
        const store = new EntityStore('media', 'mediaService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.name = 'Media Name';

        entity.save().then(() => {
            const mediaService = Application.getContainer('service').mediaService;

            const testUrl = `${process.env.APP_URL}/api/v1/_info/entity-schema.json`;

            mediaService.uploadMediaFromUrl(entity.id, testUrl, 'json').then(() => {
                store.getList({
                    page: 1,
                    limit: 1
                }, true).then((response) => {
                    expect(response.items.length).toBe(1);
                    expect(response.items[0].hasFile).toBe(true);

                    entity.delete(true).then(() => {
                        done();
                    });
                }).catch((err) => {
                    done(err);
                });
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    itAsync('should get a list and don\'t load associations', (done) => {
        const store = new EntityStore('media', 'mediaService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.name = 'Media Name';

        entity.save().then(() => {
            const mediaService = Application.getContainer('service').mediaService;

            const testUrl = `${process.env.APP_URL}/api/v1/_info/entity-schema.json`;

            mediaService.uploadMediaFromUrl(entity.id, testUrl, '.json').then(() => {
                store.getList({
                    page: 1,
                    limit: 1
                }).then((response) => {
                    expect(response.items.length).toBe(1);
                    expect(response.items[0].thumbnails.length).toBe(0);

                    entity.delete(true).then(() => {
                        done();
                    });
                }).catch((err) => {
                    done(err);
                });
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    itAsync('should accept a sort by and sort direction parameter', (done) => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        store.getList({
            page: 1,
            limit: 10,
            sortBy: 'currency.name',
            sortDirection: 'ASC'
        }).then(() => {
            done();
        }).catch((err) => {
            done(err);
        });
    });

    test('should create a new empty local entity', () => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        const entity = store.create();
        entity.factor = 6844.41;
        entity.decimalPrecision = 2;
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        const storeEntry = store.store[entity.id];

        expect(storeEntry.id).toBe(entity.id);
        expect(storeEntry.factor).toBe(entity.factor);
        expect(storeEntry.symbol).toBe(entity.symbol);
        expect(storeEntry.shortName).toBe(entity.shortName);
        expect(storeEntry.name).toBe(entity.name);
    });

    test('should add entity to the store', () => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        const entity = new EntityProxy('currency', 'currencyService');
        entity.setLocalData({
            factor: 6844.41,
            decimalPrecision: 2,
            symbol: 'Ƀ',
            shortName: 'BTC',
            name: 'Bitcoin'
        });

        store.add(entity);

        const storeEntity = store.store[entity.id];

        expect(entity).toBe(storeEntity);
    });

    test('should duplicate the entity', () => {
        const store = new EntityStore('tax', 'taxService', EntityProxy);

        const tax = new EntityProxy('tax', 'taxService');
        tax.setData({
            name: 'Test tax rate',
            taxRate: 99.98
        });
        const product = tax.associations.products.create();

        store.add(tax);

        const newTax = store.duplicate(tax.id);

        expect(newTax.id).not.equals(tax.id);
        expect(newTax.name).equals(tax.name);
        expect(newTax.taxRate).equals(tax.taxRate);
        /* eslint no-unused-expressions: 0 */
        expect(newTax.products).to.be.empty;
        expect(typeof newTax.associations.products.store[product.id]).toBe('undefined');
    });

    test('should duplicate the entity with associations', () => {
        const store = new EntityStore('tax', 'taxService', EntityProxy);

        const tax = new EntityProxy('tax', 'taxService');
        tax.setData({
            name: 'Test tax rate',
            taxRate: 99.98
        });
        const product = tax.associations.products.create();

        store.add(tax);

        const newTax = store.duplicate(tax.id, true);

        expect(newTax.id).not.equals(tax.id);
        expect(newTax.name).equals(tax.name);
        expect(newTax.taxRate).equals(tax.taxRate);
        expect(newTax.products).toHaveLength(1);
        expect(newTax.products[0].id).equals(product.id);
        expect(typeof newTax.associations.products.store[product.id]).toBe('object');
    });

    xitAsync('should handle multiple entityversions in parallel', (done) => {
        const entityStore = new EntityStore('product', 'productService', EntityProxy);
        const entityOriginal = entityStore.create({ name: 'best' });

        entityOriginal.versionize().then((entityVersioned) => {
            entityVersioned.name = 'test';
            entityVersioned.save().then(() => {
                // force complete reload
                entityStore.removeAll();
                const loadedOriginal = entityStore.getById(entityOriginal.id, true, entityOriginal.versionId);
                const loadedVersioned = entityStore.getById(entityVersioned.id, true, entityVersioned.versionId);

                Promise.all([loadedOriginal, loadedVersioned]).then(() => {
                    expect(loadedOriginal.versionId).to.be.unequal(loadedVersioned.versionId);
                    expect(loadedOriginal.name).toBe('best');
                    expect(loadedVersioned.name).toBe('test');
                    done();
                }).catch((error) => {
                    done(error);
                });
            }).catch((error) => {
                done(error);
            });
        });
    });
});
