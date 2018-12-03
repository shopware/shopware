import EntityStore from 'src/core/data/EntityStore';
import EntityProxy from 'src/core/data/EntityProxy';
import ApiService from 'src/core/service/api/api.service';

import { itAsync } from '../../../async-helper';

const Entity = Shopware.Entity;
const State = Shopware.State;
const Application = Shopware.Application;

describe('core/data/EntityStore.js', () => {
    it('should be iterate over the entities and create entity stores for each entity', () => {
        const definitions = Entity.getDefinitionRegistry();
        const definitionKeys = [...definitions.keys()];

        const stores = State.getStoreRegistry();
        const storeKeys = [...stores.keys()];

        definitionKeys.forEach((key) => {
            expect(storeKeys.includes(key)).to.be.equal(true);
        });
    });

    it('should initialize an EntityStore using a predefined api service', () => {
        const store = new EntityStore('product', 'productService', EntityProxy);

        expect(store.apiService).to.be.an('object');
        expect(store.store).to.be.an('object');
        expect(store.isLoading).to.be.equal(false);
        expect(store.entityName).to.be.equal('product');
    });

    it('should initialized an EntityStore using an instance of an api service', () => {
        const initContainer = Application.getContainer('init');
        const serviceContainer = Application.getContainer('service');

        const store = new EntityStore('product', new ApiService(
            initContainer.httpClient,
            serviceContainer.loginService,
            'product'
        ), EntityProxy);

        expect(store.apiService).to.be.an('object');
        expect(store.store).to.be.an('object');
        expect(store.isLoading).to.be.equal(false);
        expect(store.entityName).to.be.equal('product');
    });

    it('should create a new entity in the store', () => {
        const store = new EntityStore('product', 'productService', EntityProxy);

        const entity = store.create();

        expect(entity.isLocal).to.be.equal(true);
        expect(entity.isDeleted).to.be.equal(false);
        expect(entity.isLoading).to.be.equal(false);
        expect(entity.id.length).to.be.equal(32);

        const newEntity = store.create(entity.id);
        expect(newEntity.id.length).to.be.equal(32);

        // it should be the same entity
        expect(entity.id).to.be.equal(newEntity.id);
    });

    itAsync('should create a new entity in the store and save it to the server', (done) => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        // Create a new entry
        const entity = store.create();
        entity.factor = 6844.41;
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then((response) => {
            expect(response.id).to.be.equal(entity.id);
            expect(response.factor).to.be.equal(entity.factor);
            expect(response.symbol).to.be.equal(entity.symbol);
            expect(response.shortName).to.be.equal(entity.shortName);
            expect(response.name).to.be.equal(entity.name);

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
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then((response) => {
            store.getByIdAsync(response.id).then((storeEntity) => {
                expect(entity.id).to.be.equal(storeEntity.id);
                expect(storeEntity.symbol).to.be.equal('Ƀ');
                expect(storeEntity.shortName).to.be.equal('BTC');
                expect(storeEntity.name).to.be.equal('Bitcoin');

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
                    expect(response.total).to.be.equal(totalBeforeSave + 1);

                    if (itemsBeforeSave.length < limit) {
                        expect(response.items.length).to.be.equal(itemsBeforeSave.length + 1);
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
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        entity.save().then(() => {
            store.getList({
                page: 1,
                limit: 1,
                term: 'Bitcoin'
            }).then((response) => {
                expect(response.items.length).to.be.equal(1);

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

            const testUrl = `${process.env.BASE_PATH}/api/v1/_info/entity-schema.json`;

            mediaService.uploadMediaFromUrl(entity.id, testUrl, 'json').then(() => {
                store.getList({
                    page: 1,
                    limit: 1
                }, true).then((response) => {
                    expect(response.items.length).to.be.equal(1);
                    expect(response.items[0].hasFile).to.be.equal(true);

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

            const testUrl = `${process.env.BASE_PATH}/api/v1/_info/entity-schema.json`;

            mediaService.uploadMediaFromUrl(entity.id, testUrl, '.json').then(() => {
                store.getList({
                    page: 1,
                    limit: 1
                }).then((response) => {
                    expect(response.items.length).to.be.equal(1);
                    expect(response.items[0].thumbnails.length).to.be.equal(0);

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

    it('should create a new empty local entity', () => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        const entity = store.create();
        entity.factor = 6844.41;
        entity.symbol = 'Ƀ';
        entity.shortName = 'BTC';
        entity.name = 'Bitcoin';

        const storeEntry = store.store[entity.id];

        expect(storeEntry.id).to.be.equal(entity.id);
        expect(storeEntry.factor).to.be.equal(entity.factor);
        expect(storeEntry.symbol).to.be.equal(entity.symbol);
        expect(storeEntry.shortName).to.be.equal(entity.shortName);
        expect(storeEntry.name).to.be.equal(entity.name);
    });

    it('should add entity to the store', () => {
        const store = new EntityStore('currency', 'currencyService', EntityProxy);

        const entity = new EntityProxy('currency', 'currencyService');
        entity.setLocalData({
            factor: 6844.41,
            symbol: 'Ƀ',
            shortName: 'BTC',
            name: 'Bitcoin'
        });

        store.add(entity);

        const storeEntity = store.store[entity.id];

        expect(entity).to.be.deep.equal(storeEntity);
    });
});
