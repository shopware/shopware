/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { handleFactory, send } from '@shopware-ag/meteor-admin-sdk/es/channel';
import SerializerFactory from '@shopware-ag/meteor-admin-sdk/es/_internals/serializer';
import Entity from 'src/core/data/entity.data';
import { getPublishedDataSets, publishData, deepCloneWithEntity } from 'src/core/service/extension-api-data.service';
import EntityCollection from 'src/core/data/entity-collection.data';
import lodash from 'lodash';

lodash.debounce = jest.fn(fn => fn);

const serializeEntity = SerializerFactory({
    handleFactory: handleFactory,
    send: send,
}).serialize;

describe('core/service/extension-api-data.service.ts', () => {
    it('should keep functions on entity', async () => {
        const entity = new Entity(
            Shopware.Utils.createId(),
            'jest',
            {
                name: 'jest',
            },
        );

        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    jest: entity,
                };
            },
        });

        // Assert before publish
        expect(typeof entity.getDraft).toBe('function');

        publishData({
            id: 'jest',
            path: 'jest',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(typeof entity.getDraft).toBe('function');
    });

    it('should update entity', async () => {
        const entity = new Entity(
            Shopware.Utils.createId(),
            'jestupdate',
            {
                name: 'beforeupdate',
            },
        );

        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    jest: entity,
                };
            },
        });

        expect(entity.name).toBe('beforeupdate');

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const e = new Entity(
                Shopware.Utils.createId(),
                'jestupdate',
                {
                    name: 'beforeupdate',
                },
            );

            e.name = 'updated';

            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: serializeEntity(e),
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'jest',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(entity.name).toBe('updated');
    });

    it('should keep functions on collection', async () => {
        const collection = new EntityCollection(
            'jest',
            'jest',
            {},
        );

        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    collection,
                };
            },
        });

        // Assert before publish
        expect(typeof collection.getIds).toBe('function');

        publishData({
            id: 'jest',
            path: 'collection',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(typeof collection.getIds).toBe('function');
    });

    it('should update collection', async () => {
        const collection = new EntityCollection(
            'jest',
            'jest',
            {},
        );

        collection.add(new Entity(
            Shopware.Utils.createId(),
            'jest',
            {
                id: 1,
                name: 'jest1',
            },
        ));

        collection.add(new Entity(
            Shopware.Utils.createId(),
            'jest',
            {
                id: 2,
                name: 'jest2',
            },
        ));

        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    collection,
                };
            },
        });

        // Assert before publish
        expect(collection[0].name).toBe('jest1');
        expect(collection[1].name).toBe('jest2');

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: [
                        {
                            name: 'jest1updated',
                        },
                        null,
                    ],
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'collection',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(collection[0].name).toBe('jest1updated');
        expect(collection[1].name).toBe('jest2');
    });

    it('should update scalar value', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        // Assert before publish
        expect(wrapper.vm.count).toBe(42);

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: 1337,
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(wrapper.vm.count).toBe(1337);
    });

    it('should update nested scalar value', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    jest: {
                        nest: {
                            count: 42,
                        },
                    },
                };
            },
        });

        // Assert before publish
        expect(wrapper.vm.jest.nest.count).toBe(42);

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: 1337,
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'jest.nest.count',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(wrapper.vm.jest.nest.count).toBe(1337);
    });

    it('should be able to publish multiple times for same component', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        jest.spyOn(console, 'error').mockImplementation(() => {});

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper.vm,
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper.vm,
        });

        expect(console.error).toHaveBeenCalledTimes(0);
    });

    it('should fail to publish registered set different components', async () => {
        const wrapper1 = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        const wrapper2 = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        jest.spyOn(console, 'error').mockImplementation((message) => {
            expect(message).toBe('The dataset id "jest" you tried to publish is already registered.');
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper1.vm,
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper2.vm,
        });

        expect(console.error).toHaveBeenCalledTimes(1);
    });

    it('should return published datasets', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper.vm,
        });

        const publishedDataSets = getPublishedDataSets();
        expect(publishedDataSets).toHaveLength(1);
        expect(publishedDataSets[0].id).toBe('jest');
        expect(publishedDataSets[0].data).toBe(42);
    });

    it('should ignore updates for wrong published paths', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: 1337,
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: '.',
            scope: wrapper.vm,
        });

        expect(wrapper.vm.count).toBe(42);
    });

    it('should add to collection', async () => {
        const collection = new EntityCollection(
            'jest',
            'jest',
            {},
        );

        collection.add(new Entity(
            Shopware.Utils.createId(),
            'jest',
            {
                id: 1,
                name: 'jest1',
            },
        ));

        collection.add(new Entity(
            Shopware.Utils.createId(),
            'jest',
            {
                id: 2,
                name: 'jest2',
            },
        ));

        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    collection,
                };
            },
        });

        // Assert before publish
        expect(collection).toHaveLength(2);
        expect(collection[0].name).toBe('jest1');
        expect(collection[1].name).toBe('jest2');

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: [
                        null,
                        null,
                        {
                            id: 3,
                            name: 'jest3',
                        },
                    ],
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'collection',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        expect(collection).toHaveLength(3);
        expect(collection[0].name).toBe('jest1');
        expect(typeof collection[0].getDraft).toBe('function');
        expect(collection[1].name).toBe('jest2');
        expect(typeof collection[1].getDraft).toBe('function');
        expect(collection[2].name).toBe('jest3');
    });

    it('should deepClone with Entities', async () => {
        const originalValue = {
            name: 'Shopware',
            age: 21,
            product: new Entity('foo', 'product', {
                name: 'T-Shirt',
                price: 10,
                description: 'A T-Shirt',
            }),
            mediaCollection: new EntityCollection(
                'demo/media',
                'media',
                {
                    auth: {
                        token: 'mySecretToken',
                    },
                },
                null,
                [
                    new Entity('image1', 'media', {
                        url: 'https://shopware.com/image1.jpg',
                        tags: new EntityCollection('image1/tags', 'tag', {}, null, [
                            new Entity('tag1', 'tag', {
                                name: 'Shopware',
                            }),
                            new Entity('tag2', 'tag', {
                                name: 'Shopware AG',
                            }),
                            new Entity('tag3', 'tag', {
                                name: 'Shopware Community',
                            }),
                        ]),
                    }),
                    new Entity('image2', 'media', {
                        url: 'https://shopware.com/image2.jpg',
                        tags: new EntityCollection('image2/tags', 'tag', {}, null, [
                            new Entity('tag4', 'tag', {
                                name: 'Shopware',
                            }),
                            new Entity('tag5', 'tag', {
                                name: 'Shopware AG',
                            }),
                            new Entity('tag6', 'tag', {
                                name: 'Shopware Community',
                            }),
                        ]),
                    }),
                ],
            ),
        };

        const clonedValue = deepCloneWithEntity(originalValue);

        // Should serialize to the same values
        expect(JSON.stringify(clonedValue)).toEqual(JSON.stringify(originalValue));
        // Should have different EntityCollections and Entities
        expect(clonedValue.product).not.toBe(originalValue.product);
        expect(clonedValue.mediaCollection).not.toBe(originalValue.mediaCollection);
        expect(clonedValue.mediaCollection[0]).not.toBe(originalValue.mediaCollection[0]);
        expect(clonedValue.mediaCollection[0].tags).not.toBe(originalValue.mediaCollection[0].tags);
        expect(clonedValue.mediaCollection[0].tags[0]).not.toBe(originalValue.mediaCollection[0].tags[0]);
        // Should not contain the context from the cloned EntityCollection
        expect(originalValue.mediaCollection.context.auth).toEqual({
            token: 'mySecretToken',
        });
        expect(clonedValue.mediaCollection.context.auth).toBeUndefined();
    });

    it('should not update value after component gets unmounted', async () => {
        const wrapper = mount({
            template: '<h1>jest</h1>',
            data() {
                return {
                    count: 42,
                };
            },
        });

        // Assert before publish
        expect(wrapper.vm.count).toBe(42);

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: 1337,
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        publishData({
            id: 'jest',
            path: 'count',
            scope: wrapper.vm,
        });

        await flushPromises();

        // Assert after publish
        let publishedDataSets = getPublishedDataSets();
        expect(publishedDataSets).toHaveLength(1);
        expect(publishedDataSets[0].data).toBe(1337);

        // Assert after publish
        expect(wrapper.vm.count).toBe(1337);

        // Destroy component
        wrapper.unmount();
        await flushPromises();

        jest.spyOn(window, 'addEventListener').mockImplementationOnce((event, handler) => {
            const data = {
                _type: 'datasetUpdate',
                _data: {
                    id: 'jest',
                    data: 1338,
                },
                _callbackId: Shopware.Utils.createId(),
            };

            handler({ data: JSON.stringify(data) });
        });

        // Change value in wrapper that is already destroyed
        wrapper.vm.count = 1338;

        await flushPromises();

        // Assert in publishedDataSets that it was removed
        publishedDataSets = getPublishedDataSets();
        expect(publishedDataSets).toHaveLength(0);
    });
});
