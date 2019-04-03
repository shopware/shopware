import EntityProxy from 'src/core/data/EntityProxy';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { itAsync } from '../../../async-helper';

const State = Shopware.State;
const Application = Shopware.Application;

const serviceContainer = Application.getContainer('service');

describe('core/data/EntityProxy.js', () => {
    test(
        'should create an entity without initial data (empty entity should be created)',
        () => {
            const productEntity = new EntityProxy('product', serviceContainer.productService);

            expect(productEntity).toHaveProperty('createdAt');
            expect(productEntity).toHaveProperty('updatedAt');
            expect(productEntity).toHaveProperty('manufacturerId');
            expect(productEntity).toHaveProperty('taxId');
            expect(productEntity).toHaveProperty('price');

            expect(Object.keys(productEntity.getChanges()).length).toBe(0);

            expect(productEntity.isLocal).toBe(true);
        }
    );

    test('should create an entity and silently set initial local data', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        productEntity.setLocalData({
            active: true,
            allowNotification: true,
            name: 'A simple product',
            description: 'Lorem ipsum dolor sit amet'
        }, true, false);

        expect(Object.keys(productEntity.getChanges()).length).toBe(0);

        expect(productEntity.name).toBe('A simple product');
        expect(productEntity.description).toBe('Lorem ipsum dolor sit amet');
        expect(productEntity.active).toBe(true);
        expect(productEntity.allowNotification).toBe(true);
    });

    test('should create an entity with local changes', () => {
        const categoryEntity = new EntityProxy('category', serviceContainer.categoryService);

        categoryEntity.name = 'Example category Edited';
        expect(Object.keys(categoryEntity.getChanges()).length).toBe(1);
        expect(categoryEntity.getChanges().name).toBe('Example category Edited');
    });

    test('should generate association stores for the entity', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);
        const associatedProps = productEntity.associatedEntityPropNames;

        Object.keys(productEntity.associations).forEach((associationKey) => {
            expect(associatedProps.indexOf(associationKey) !== -1).toBe(true);
        });
    });

    test('should return a generated association store', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);
        const associatedProps = productEntity.associatedEntityPropNames;

        associatedProps.forEach((associationKey) => {
            const store = productEntity.getAssociation(associationKey);

            expect(typeof store.store).toBe('object');
            expect(store.isLoading).toBe(false);
            expect(typeof store.parentEntity).toBe('object');
            expect(store.associationKey).toBe(associationKey);
        });
    });

    test('should get the changes of the entity without associations', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        // Trigger a change
        productEntity.name = 'Test product';

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        const changes = productEntity.getChanges();

        expect(changes.name).toBe('Test product');
        expect(typeof changes.categories).toBe('undefined');
    });

    test('should get the changes of the entity including associations', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        // Trigger a change
        productEntity.name = 'Test product';

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        const changes = productEntity.getChanges();
        const changedAssociations = productEntity.getChangedAssociations();

        Object.assign(changes, changedAssociations);

        expect(changes.name).toBe('Test product');
        expect(changes.categories).toBeInstanceOf(Array);
        expect(changes.categories.length).toBe(1);
        expect(changes.categories[0].name).toBe('Test category');
    });

    test(
        'should get the changes of the entity including associations recursively',
        () => {
            const pageEntity = new EntityProxy('cms_page', serviceContainer.cmsPageService);

            pageEntity.name = 'Test page';
            pageEntity.type = 'landingpage';
            pageEntity.config.backgroundColor = '#ffffff';

            const blockStore = pageEntity.getAssociation('blocks');
            const blockEntity = blockStore.create();

            blockEntity.position = 1;
            blockEntity.config.backgroundColor = '#000000';
            blockEntity.config.cssClass = '.test-class';

            const slotStore = blockEntity.getAssociation('slots');
            const slotEntity = slotStore.create();

            slotEntity.type = 'text';
            slotEntity.config.content = 'Lorem ipsum';

            const changes = pageEntity.getChanges();
            const changedAssociations = pageEntity.getChangedAssociations();

            Object.assign(changes, changedAssociations);

            expect(changes.name).toBe('Test page');
            expect(changes.type).toBe('landingpage');
            expect(changes.config.backgroundColor).toBe('#ffffff');
            expect(changes.blocks).toBeInstanceOf(Array);
            expect(changes.blocks.length).toBe(1);
            expect(changes.blocks[0].position).toBe(1);
            expect(changes.blocks[0].config.backgroundColor).toBe('#000000');
            expect(changes.blocks[0].config.cssClass).toBe('.test-class');
            expect(changes.blocks[0].slots).toBeInstanceOf(Array);
            expect(changes.blocks[0].slots.length).toBe(1);
            expect(changes.blocks[0].slots[0].type).toBe('text');
            expect(changes.blocks[0].slots[0].config.content).toBe('Lorem ipsum');
        }
    );

    itAsync('should save the entity', (done) => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        const taxEntity = new EntityProxy('tax', serviceContainer.taxService);
        taxEntity.setLocalData({
            name: 'Test tax rate',
            taxRate: 99.98
        });

        const manufacturerEntity = new EntityProxy('product_manufacturer', serviceContainer.productManufacturerService);
        manufacturerEntity.setLocalData({
            name: 'Test manufacturer'
        });

        // Trigger changes
        productEntity.tax = taxEntity.getChanges();
        productEntity.tax.id = taxEntity.id;
        productEntity.stock = 10;
        productEntity.manufacturer = manufacturerEntity.getChanges();
        productEntity.manufacturer.id = manufacturerEntity.id;

        productEntity.name = 'Sample product';
        productEntity.price = {
            gross: 12,
            net: 11
        };

        productEntity.save().then((response) => {
            expect(response.errors.length).toBe(0);
            expect(response.name).toBe('Sample product');

            expect(response.price).to.deep.include({
                gross: 12,
                net: 11
            });

            productEntity.delete(true)
                .then(() => {
                    return manufacturerEntity.delete(true);
                })
                .then(() => {
                    return taxEntity.delete(true);
                })
                .then(() => {
                    done();
                })
                .catch((error) => {
                    done(error);
                });
        }).catch((error) => {
            done(error);
        });
    }, 50000);

    itAsync('should reset the field value to null', (done) => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        const taxEntity = new EntityProxy('tax', serviceContainer.taxService);
        taxEntity.setLocalData({
            name: 'Test tax rate',
            taxRate: 99.98
        });

        const manufacturerEntity = new EntityProxy('product_manufacturer', serviceContainer.productManufacturerService);
        manufacturerEntity.setLocalData({
            name: 'Test manufacturer'
        });

        // Trigger changes
        productEntity.tax = taxEntity.getChanges();
        productEntity.tax.id = taxEntity.id;
        productEntity.stock = 10;
        productEntity.manufacturer = manufacturerEntity.getChanges();
        productEntity.manufacturer.id = manufacturerEntity.id;

        productEntity.name = 'Sample product';
        productEntity.price = {
            gross: 12,
            net: 11
        };
        productEntity.releaseDate = '2019-03-20T12:00:00+00:00';

        // Save entity
        productEntity.save().then((response) => {
            expect(response.errors.length).toBe(0);
            expect(response.name).toBe('Sample product');

            expect(response.price).to.deep.include({
                gross: 12,
                net: 11
            });

            // Change release date to an empty string
            productEntity.releaseDate = '';

            productEntity.save().then((secondResponse) => {
                expect(secondResponse.errors.length).toBe(0);
                expect(secondResponse.releaseDate).toBe(null);

                productEntity.delete(true)
                    .then(() => {
                        return manufacturerEntity.delete(true);
                    })
                    .then(() => {
                        return taxEntity.delete(true);
                    })
                    .then(() => {
                        done();
                    })
                    .catch((error) => {
                        done(error);
                    });
            });
        }).catch((error) => {
            done(error);
        });
    }, 50000);

    test('should remove itself from the store', () => {
        const store = State.getStore('product');
        const entity = store.create();

        expect(entity.store).toBe(store);
        expect(entity.store.store[entity.id]).toBe(entity);

        expect(entity.remove()).toBe(true);

        expect(typeof store[entity.id]).toBe('undefined');

        const productEntity = new EntityProxy('product', serviceContainer.productService);
        expect(productEntity.remove()).toBe(false);
    });

    itAsync('should delete the entity (direct delete)', (done) => {
        const taxEntity = new EntityProxy('tax', serviceContainer.taxService);

        taxEntity.name = 'Test tax rate';
        taxEntity.taxRate = 99.98;

        taxEntity.save().then(() => {
            taxEntity.delete(true).then(() => {
                done();
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });

    test('should validate the required fields', () => {
        const invalidProductEntity = new EntityProxy('product', serviceContainer.productService);
        const validate = invalidProductEntity.validate();

        expect(validate).toBe(false);
    });

    test('should handle changes in JSON fields correctly', () => {
        const product = new EntityProxy('product', serviceContainer.productService);

        product.setData({
            price: {
                gross: null,
                net: 0,
                linked: false
            },
            listingPrices: {
                price: 50,
                linked: true
            }
        });

        product.setLocalData({
            price: {
                gross: 90
            },
            listingPrices: {
                purchase: 30
            },
            testProp: {
                test: true
            }
        });

        const changes = product.getChanges();

        expect(typeof changes.price).toBe('object');
        expect(changes.price.net).toBe(0);
        expect(changes.price.linked).toBe(false);
        expect(changes.price.gross).toBe(90);

        expect(typeof changes.testProp).toBe('undefined');
    });

    itAsync('should hydrate associations as EntityProxy', (done) => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        productEntity.setLocalData({
            name: 'Test Product',
            price: {
                gross: 90,
                net: 80
            }
        });

        const taxEntity = new EntityProxy('tax', serviceContainer.taxService);
        taxEntity.setLocalData({ name: 'Test tax rate', taxRate: 99.98 });
        productEntity.tax = taxEntity.getChanges();
        productEntity.tax.id = taxEntity.id;
        productEntity.stock = 1;

        const manufacturerEntity = new EntityProxy('product_manufacturer', serviceContainer.productManufacturerService);
        manufacturerEntity.setLocalData({ name: 'Test manufacturer' });
        productEntity.manufacturer = manufacturerEntity.getChanges();
        productEntity.manufacturer.id = manufacturerEntity.id;

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        productEntity.save().then(() => {
            serviceContainer.productService.getList({
                page: 1,
                limit: 1,
                criteria: CriteriaFactory.equals('id', productEntity.id),
                associations: {
                    categories: {
                        page: 1,
                        limit: 1
                    }
                }
            }).then((response) => {
                const data = response.data[0];
                const loadedProduct = new EntityProxy('product', serviceContainer.productService);

                loadedProduct.setData(data, false, true);
                const category = loadedProduct.categories[0];

                expect(typeof category.draft).toBe('object');
                expect(typeof Object.getOwnPropertyDescriptor(category, 'draft')).toBe('undefined');

                productEntity.delete(true)
                    .then(() => {
                        return manufacturerEntity.delete(true);
                    })
                    .then(() => {
                        return taxEntity.delete(true);
                    })
                    .then(() => {
                        return serviceContainer.categoryService.delete(category.id);
                    })
                    .then(() => {
                        done();
                    })
                    .catch((error) => {
                        done(error);
                    });
            }).catch((err) => {
                done(err);
            });
        }).catch((err) => {
            done(err);
        });
    });
});
