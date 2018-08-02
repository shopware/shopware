/* eslint-disable */

import EntityProxy from 'src/core/data/EntityProxy';
import utils from 'src/core/service/util.service';
import { itAsync } from '../../../async-helper';

const State = Shopware.State;

describe('core/data/EntityProxy.js', () => {
    it('should create an entity without initial data (empty entity should be created)', () => {
        const productEntity = new EntityProxy('product', 'productService');

        expect(productEntity).to.have.property('catalogId');
        expect(productEntity).to.have.property('createdAt');
        expect(productEntity).to.have.property('updatedAt');
        expect(productEntity).to.have.property('manufacturerId');
        expect(productEntity).to.have.property('taxId');
        expect(productEntity).to.have.property('price');

        // We shouldn't have any changes yet
        expect(Object.keys(productEntity.getChanges()).length).to.be.equal(0);

        expect(productEntity.isNew).to.be.equal(true);
    });

    it('should create an entity with initial data', () => {
        const productWithDataEntity = new EntityProxy('product', 'productService', {
            id: utils.createId(),
            active: true,
            allowNotification: true,
            name: 'A simple product',
            description: 'Lorem ipsum dolor sit amet'
        });

        // We shouldn't have any changes yet
        expect(Object.keys(productWithDataEntity.getChanges()).length).to.be.equal(0);

        expect(productWithDataEntity.name).to.be.equal('A simple product');
        expect(productWithDataEntity.description).to.be.equal('Lorem ipsum dolor sit amet');
        expect(productWithDataEntity.active).to.be.equal(true);
        expect(productWithDataEntity.allowNotification).to.be.equal(true);

        const productWithoutIdEntity = new EntityProxy('product', 'productService', {
            active: true,
            allowNotification: true
        });

        expect(productWithoutIdEntity.active).to.be.equal(false);
        expect(productWithoutIdEntity.allowNotification).to.be.equal(false);
    });


    it('should create an entity with initial data with association keys', () => {
        const categoryEntity = new EntityProxy('category', 'categoryService', {
            id: utils.createId(),
            name: 'Example category'
        });

        const productWithoutDataAssociationEntity = new EntityProxy('product', 'productService');

        productWithoutDataAssociationEntity.initData({
            id: utils.createId(),
            active: true,
            allowNotification: true,
            name: 'A simple product',
            description: 'Lorem ipsum dolor sit amet',
            categories: [categoryEntity]
        });

        const productWithDataAssociationEntity = new EntityProxy('product', 'productService');
        productWithDataAssociationEntity.initData({
            id: utils.createId(),
            active: true,
            allowNotification: true,
            name: 'A simple product',
            description: 'Lorem ipsum dolor sit amet',
            categories: [categoryEntity]
        }, false);

        expect(productWithDataAssociationEntity.categories.length).to.be.equal(1);
        expect(productWithoutDataAssociationEntity.categories.length).to.be.equal(0);

        expect(Object.keys(productWithoutDataAssociationEntity.getChanges()).length).to.be.equal(0);
        expect(Object.keys(productWithDataAssociationEntity.getChanges()).length).to.be.equal(0);
    });

    it('should create an entity with changes in place', () => {
        const categoryEntity = new EntityProxy('category', 'categoryService', {
            id: utils.createId(),
            name: 'Example category'
        });

        categoryEntity.name =  'Example category Edited';
        expect(Object.keys(categoryEntity.getChanges()).length).to.be.equal(1);

    });

    it('should generate association stores for the entity', () => {
        const productEntity = new EntityProxy('product', 'productService');
        const requiredStores = productEntity.associatedProperties;

        const keys = Array.from(productEntity.associationStores.keys());

        keys.forEach((storeKey) => {
            expect(requiredStores.indexOf(storeKey) !== -1).to.be.equal(true);
        });
    });

    it('should return a generated association store', () => {
        const productEntity = new EntityProxy('product', 'productService');
        const storeKeys = productEntity.associatedProperties;

        storeKeys.forEach((storeKey) => {
            const store = productEntity.getAssociationStore(storeKey);

            expect(store.store).to.be.an('object');
            expect(store.isLoading).to.be.equal(false);
            expect(store.isLoading).to.be.equal(false);
            expect(store.$parent).to.be.an('object');
            expect(store.$type).to.be.equal(storeKey);
        });
    });

    it('should get the changes without association', () => {
        const productEntity = new EntityProxy('product', 'productService');

        // Trigger a change
        productEntity.name = 'Test';

        const categoryEntity = new EntityProxy('category', 'categoryService', {
            id: utils.createId(),
            name: 'Example category'
        });

        productEntity.categories.push(categoryEntity);

        const changes = productEntity.getChanges();

        expect(changes).to.deep.include({
            name: 'Test'
        });

        const changesWithAssociations = productEntity.getChanges(true);

        expect(changesWithAssociations).to.be.an('object');
        expect(changesWithAssociations.categories).to.be.an('array');
    });

    itAsync('should save the entity', (done) => {
        const productEntity = new EntityProxy('product', 'productService');

        const taxEntity = new EntityProxy('tax', 'taxService', {
            id: utils.createId(),
            name: 'Insane tax rate',
            taxRate: 99.98
        });
        taxEntity.isNew = true;

        const catalogEntity = new EntityProxy('catalog', 'catalogService');
        catalogEntity.name = 'Sample catalog';

        const manufacturerEntity = new EntityProxy('product_manufacturer',  'productManufacturerService', {
            id: utils.createId(),
            catalogId: catalogEntity.id,
            name: 'Sample manufactruer'
        });
        manufacturerEntity.isNew = true;

        // Trigger changes
        productEntity.tax = taxEntity;
        productEntity.manufacturer = manufacturerEntity;

        productEntity.name = 'Sample product';
        productEntity.catalogId = catalogEntity.id;
        productEntity.taxId = taxEntity.id;
        productEntity.manufacturerId = manufacturerEntity.id;
        productEntity.price = {
            gross: 12,
            net: 11
        };

        catalogEntity.save().then(() => {
            productEntity.save().then((response) => {
                expect(response.errors.length).to.be.equal(0);
                expect(response.name).to.be.equal('Sample product');
                expect(response.taxId).to.be.equal(taxEntity.id);
                expect(response.manufacturerId).to.be.equal(manufacturerEntity.id);
                expect(response.catalogId).to.be.equal(catalogEntity.id);

                expect(response.price).to.deep.include({
                    gross: 12,
                    net: 11
                });
                done();
            });
        });
    });

    it('should remove itself from the store', () => {
        const store = State.getStore('product');
        const entity = store.create();

        expect(entity.$store).to.be.equal(store);
        expect(entity.$store.store[entity.id]).to.be.equal(entity);

        expect(entity.remove()).to.be.equal(true);

        expect(entity.$store.store[entity.id]).to.be.an('undefined');

        const productEntity = new EntityProxy('product', 'productService');
        expect(productEntity.remove()).to.be.equal(false);
    });

    itAsync('should delete the entity (direct delete)', (done) => {
        const taxEntity = new EntityProxy('tax', 'taxService');

        taxEntity.name = 'Insane tax rate';
        taxEntity.taxRate = 99.98;
        taxEntity.isNew = true;
        taxEntity.save().then(() => {
            taxEntity.delete(true).then(() => {
                done();
            });
        });
    });

    it('should validate the required fields', () => {
        const invalidProductEntity = new EntityProxy('product', 'productService');
        const validate = invalidProductEntity.validate();

        expect(validate).to.be.equal(false);
    });
});
