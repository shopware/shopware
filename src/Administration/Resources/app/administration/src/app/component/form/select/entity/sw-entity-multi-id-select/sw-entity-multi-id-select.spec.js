/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import Criteria from 'src/core/data/criteria.data';
import utils from 'src/core/service/util.service';

const fixture = [
    {
        id: utils.createId(),
        name: 'first entry',
        active: true,
        variation: [{ group: 'Color', option: 'Red' }],
    },
    {
        id: utils.createId(),
        name: 'second entry',
        active: false,
    },
];

function getCollection(entity = 'testEntity', route = '/test-entity') {
    return new EntityCollection(route, entity, null, new Criteria(1, 25), fixture, fixture.length, null);
}

function getEmptyCollection() {
    return new EntityCollection('/test-entity', 'testEntity', null, new Criteria(1, 25), [], 0, null);
}

async function createWrapper(propsOverride = {}, stubsOverride = {}) {
    return mount(await wrapTestComponent('sw-entity-multi-id-select', { sync: true }), {
        props: {
            value: getCollection().getIds(),
            repository: {
                route: '/test-entity',
                entityName: 'testEntity',
                search: () => {
                    return Promise.resolve(getCollection());
                },
            },
            ...propsOverride,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => {
                        return {
                            get: (value) => Promise.resolve({ id: value, name: value }),
                            search: () => {
                                return Promise.resolve();
                            },
                        };
                    },
                },
            },
            stubs: {
                'sw-block-field': true,
                'sw-select-selection-list': true,
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-entity-multi-select': await wrapTestComponent('sw-entity-multi-select'),
                'sw-product-variant-info': true,
                'sw-highlight-text': true,
                'sw-select-result': true,
                'sw-select-result-list': true,
                'sw-loader': true,
                ...stubsOverride,
            },
        },
    });
}

describe('components/sw-entity-multi-id-select', () => {
    it('should able to update value', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.updateIds(getCollection());
        await flushPromises();

        expect(wrapper.vm.value).toHaveLength(fixture.length);
        expect(wrapper.vm.collection).toHaveLength(fixture.length);

        await wrapper.setProps({ value: [] });
        expect(wrapper.vm.value).toHaveLength(0);
        expect(wrapper.vm.collection).toHaveLength(0);
    });

    it('should reset selected value if it is invalid', async () => {
        const wrapper = await createWrapper();
        wrapper.vm.updateIds = jest.fn();
        await wrapper.setProps({
            value: ['non-existing-id'],
            repository: {
                route: '/test-entity',
                entityName: 'testEntity',
                search: () => {
                    return Promise.resolve(getEmptyCollection());
                },
            },
        });

        expect(wrapper.vm.updateIds).toHaveBeenCalled();
    });

    it('should properly initialize with null value', async () => {
        const wrapper = await createWrapper({ value: null });
        await flushPromises();

        expect(wrapper.vm.normalizedValue).toEqual([]);
        expect(wrapper.vm.collection).toHaveLength(0);
    });

    it('should properly handle value changes from array to null', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.collection).toHaveLength(fixture.length);

        await wrapper.setProps({ value: null });
        await flushPromises();

        expect(wrapper.vm.normalizedValue).toHaveLength(0);
        expect(wrapper.vm.collection).toHaveLength(0);
    });

    it('should properly handle value changes from null to array', async () => {
        const wrapper = await createWrapper({ value: null });
        await flushPromises();

        expect(wrapper.vm.collection).toHaveLength(0);

        await wrapper.setProps({ value: getCollection().getIds() });
        await flushPromises();

        expect(wrapper.vm.normalizedValue).toHaveLength(fixture.length);
        expect(wrapper.vm.collection).toHaveLength(fixture.length);
    });

    it('should call repository.search when value changes to a different set of ids', async () => {
        const search = jest.fn(() => Promise.resolve(getCollection()));
        const repository = {
            route: '/test-entity',
            entityName: 'testEntity',
            search,
        };

        const wrapper = await createWrapper({ repository });
        await flushPromises();

        search.mockClear();

        const otherId = utils.createId();
        await wrapper.setProps({ value: [otherId] });
        await flushPromises();

        expect(search).toHaveBeenCalledTimes(1);
    });

    it('should not call repository.search when value is a new array with the same ids (deep equality)', async () => {
        const search = jest.fn(() => Promise.resolve(getCollection()));
        const repository = {
            route: '/test-entity',
            entityName: 'testEntity',
            search,
        };

        const initialIds = getCollection().getIds();
        const wrapper = await createWrapper({
            repository,
            value: initialIds,
        });
        await flushPromises();

        search.mockClear();

        const sameIdsDifferentInstance = getCollection().getIds();
        expect(sameIdsDifferentInstance).not.toBe(initialIds);
        expect([...sameIdsDifferentInstance]).toEqual([...initialIds]);

        await wrapper.setProps({ value: sameIdsDifferentInstance });
        await flushPromises();

        expect(search).not.toHaveBeenCalled();
    });

    it('should display product variant information for product entities', async () => {
        const wrapper = await createWrapper({
            repository: {
                route: '/product',
                entityName: 'product',
                search: () => {
                    return Promise.resolve(getCollection('product', '/product'));
                },
            },
        });
        await flushPromises();

        expect(wrapper.vm.displayVariants).toBe(true);
        expect(wrapper.vm.selectCriteria.associations).toEqual(
            expect.arrayContaining([
                expect.objectContaining({ association: 'options' }),
            ]),
        );
    });

    it('should render variant info in default label slots for product entities', async () => {
        const slotRenderingMultiSelectStub = {
            template: `
                <div>
                    <slot
                        name="selection-label-property"
                        :item="item"
                        label-property="name"
                    ></slot>
                    <slot
                        name="result-label-property"
                        :item="item"
                        label-property="name"
                        :get-key="getKey"
                        search-term=""
                        :highlight-search-term="false"
                    ></slot>
                </div>
            `,
            data() {
                return { item: fixture[0] };
            },
            methods: {
                getKey: (item, key) => item[key],
            },
        };

        const wrapper = await createWrapper(
            {
                repository: {
                    route: '/product',
                    entityName: 'product',
                    search: () => {
                        return Promise.resolve(getCollection('product', '/product'));
                    },
                },
            },
            {
                'sw-entity-multi-select': slotRenderingMultiSelectStub,
            },
        );
        await flushPromises();

        expect(wrapper.findAll('sw-product-variant-info-stub')).toHaveLength(2);

        const nonProductWrapper = await createWrapper(
            {},
            {
                'sw-entity-multi-select': slotRenderingMultiSelectStub,
            },
        );
        await flushPromises();

        expect(nonProductWrapper.vm.displayVariants).toBe(false);
        expect(nonProductWrapper.find('sw-product-variant-info-stub').exists()).toBe(false);
        expect(nonProductWrapper.text()).toContain(fixture[0].name);
    });
});
