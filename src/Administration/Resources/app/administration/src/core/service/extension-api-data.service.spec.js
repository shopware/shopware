/**
 * @package admin
 */

import { mount } from '@vue/test-utils';
import { handleFactory, send } from '@shopware-ag/admin-extension-sdk/es/channel';
import SerializerFactory from '@shopware-ag/admin-extension-sdk/es/_internals/serializer';
import Entity from 'src/core/data/entity.data';
import { getPublishedDataSets, publishData } from 'src/core/service/extension-api-data.service';
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper1.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
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

        wrapper.destroy();
    });
});
