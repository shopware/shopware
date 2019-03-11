import EntityProxy from 'src/core/data/EntityProxy';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import { itAsync } from '../../../async-helper';

const State = Shopware.State;
const Application = Shopware.Application;

const serviceContainer = Application.getContainer('service');

describe('core/data/EntityProxy.js', () => {
    it('should create an entity without initial data (empty entity should be created)', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        expect(productEntity).to.have.property('createdAt');
        expect(productEntity).to.have.property('updatedAt');
        expect(productEntity).to.have.property('manufacturerId');
        expect(productEntity).to.have.property('taxId');
        expect(productEntity).to.have.property('price');

        // We shouldn't have any changes yet
        expect(Object.keys(productEntity.getChanges()).length).to.be.equal(0);

        expect(productEntity.isLocal).to.be.equal(true);
    });

    it('should create an entity and silently set initial local data', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        productEntity.setLocalData({
            active: true,
            allowNotification: true,
            name: 'A simple product',
            description: 'Lorem ipsum dolor sit amet'
        }, true, false);

        // We shouldn't have any changes yet
        expect(Object.keys(productEntity.getChanges()).length).to.be.equal(0);

        expect(productEntity.name).to.be.equal('A simple product');
        expect(productEntity.description).to.be.equal('Lorem ipsum dolor sit amet');
        expect(productEntity.active).to.be.equal(true);
        expect(productEntity.allowNotification).to.be.equal(true);
    });

    it('should create an entity with local changes', () => {
        const categoryEntity = new EntityProxy('category', serviceContainer.categoryService);

        categoryEntity.name = 'Example category Edited';
        expect(Object.keys(categoryEntity.getChanges()).length).to.be.equal(1);
        expect(categoryEntity.getChanges().name).to.be.equal('Example category Edited');
    });

    it('should generate association stores for the entity', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);
        const associatedProps = productEntity.associatedEntityPropNames;

        Object.keys(productEntity.associations).forEach((associationKey) => {
            expect(associatedProps.indexOf(associationKey) !== -1).to.be.equal(true);
        });
    });

    it('should return a generated association store', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);
        const associatedProps = productEntity.associatedEntityPropNames;

        associatedProps.forEach((associationKey) => {
            const store = productEntity.getAssociation(associationKey);

            expect(store.store).to.be.an('object');
            expect(store.isLoading).to.be.equal(false);
            expect(store.parentEntity).to.be.an('object');
            expect(store.associationKey).to.be.equal(associationKey);
        });
    });

    it('should get the changes of the entity without associations', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        // Trigger a change
        productEntity.name = 'Test product';

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        const changes = productEntity.getChanges();

        expect(changes.name).to.be.equal('Test product');
        expect(changes.categories).to.be.an('undefined');
    });

    it('should get the changes of the entity including associations', () => {
        const productEntity = new EntityProxy('product', serviceContainer.productService);

        // Trigger a change
        productEntity.name = 'Test product';

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        const changes = productEntity.getChanges();
        const changedAssociations = productEntity.getChangedAssociations();

        Object.assign(changes, changedAssociations);

        expect(changes.name).to.be.equal('Test product');
        expect(changes.categories).to.be.an('array');
        expect(changes.categories.length).to.be.equal(1);
        expect(changes.categories[0].name).to.be.equal('Test category');
    });

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
        productEntity.manufacturer = manufacturerEntity.getChanges();
        productEntity.manufacturer.id = manufacturerEntity.id;

        productEntity.name = 'Sample product';
        productEntity.price = {
            gross: 12,
            net: 11
        };

        productEntity.save().then((response) => {
            expect(response.errors.length).to.be.equal(0);
            expect(response.name).to.be.equal('Sample product');

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
            expect(response.errors.length).to.be.equal(0);
            expect(response.name).to.be.equal('Sample product');

            expect(response.price).to.deep.include({
                gross: 12,
                net: 11
            });

            // Change release date to an empty string
            productEntity.releaseDate = '';

            productEntity.save().then((secondResponse) => {
                expect(secondResponse.errors.length).to.be.equal(0);
                expect(secondResponse.releaseDate).to.be.equal(null);

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

    it('should remove itself from the store', () => {
        const store = State.getStore('product');
        const entity = store.create();

        expect(entity.store).to.be.equal(store);
        expect(entity.store.store[entity.id]).to.be.equal(entity);

        expect(entity.remove()).to.be.equal(true);

        expect(store[entity.id]).to.be.an('undefined');

        const productEntity = new EntityProxy('product', serviceContainer.productService);
        expect(productEntity.remove()).to.be.equal(false);
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

    it('should validate the required fields', () => {
        const invalidProductEntity = new EntityProxy('product', serviceContainer.productService);
        const validate = invalidProductEntity.validate();

        expect(validate).to.be.equal(false);
    });

    it('should handle changes in JSON fields correctly', () => {
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

        expect(changes.price).to.be.an('object');
        expect(changes.price.net).to.be.equal(0);
        expect(changes.price.linked).to.be.equal(false);
        expect(changes.price.gross).to.be.equal(90);

        expect(changes.testProp).to.be.an('undefined');
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

        const manufacturerEntity = new EntityProxy('product_manufacturer', serviceContainer.productManufacturerService);
        manufacturerEntity.setLocalData({ name: 'Test manufacturer' });
        productEntity.manufacturer = manufacturerEntity.getChanges();
        productEntity.manufacturer.id = manufacturerEntity.id;

        const categoryEntity = productEntity.getAssociation('categories').create();
        categoryEntity.name = 'Test category';

        productEntity.save().then(() => {
            console.log('First Request');
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

                expect(category.draft).to.be.an('object');
                expect(Object.getOwnPropertyDescriptor(category, 'draft')).to.be.an('undefined');

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
