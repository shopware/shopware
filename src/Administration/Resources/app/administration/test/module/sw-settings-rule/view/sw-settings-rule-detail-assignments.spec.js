/* eslint-disable max-len */
import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-settings-rule/view/sw-settings-rule-detail-assignments';
import EntityCollection from 'src/core/data/entity-collection.data';

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

function createWrapper(entitiesWithResults = []) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});

    return shallowMount(Shopware.Component.build('sw-settings-rule-detail-assignments'), {
        localVue,
        stubs: {
            'sw-card': {
                template: '<div class="sw-card"><slot name="grid"></slot></div>'
            },
            'sw-loader': true,
            'sw-empty-state': true,
            'sw-entity-listing': {
                props: ['items'],
                template: `
<div class="sw-entity-listing-stub">
    <li v-for="(item, index) in items" :key="index">{{ item.name }}</li>
</div>`
            }
        },
        propsData: {
            ruleId: 'uuid1',
            rule: {
                name: 'Test rule',
                priority: 7,
                description: 'Lorem ipsum',
                type: ''
            }
        },
        provide: {
            repositoryFactory: {
                create: (entityName) => {
                    return {
                        search: (_, api) => {
                            const entities = [
                                { name: 'Foo' },
                                { name: 'Bar' },
                                { name: 'Baz' }
                            ];

                            if (api.inheritance) {
                                entities.push({ name: 'Inherited' });
                            }

                            if (entitiesWithResults.includes(entityName)) {
                                return Promise.resolve(createEntityCollectionMock(entityName, entities));
                            }

                            return Promise.resolve(createEntityCollectionMock(entityName));
                        }
                    };
                }
            }
        }
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-assignments', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should prepare association entities list', async () => {
        const wrapper = createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'event_action'
        ]);

        expect(wrapper.vm.associationEntities).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: expect.any(String),
                    detailRoute: expect.any(String),
                    repository: expect.any(Object),
                    gridColumns: expect.any(Array),
                    criteria: expect.any(Function),
                    loadedData: null
                })
            ])
        );
    });

    it('should try to load and assign entity data for defined entities', async () => {
        const wrapper = createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'event_action'
        ]);

        // Wait for repository request
        await wrapper.vm.$nextTick();

        const expectedEntityCollectionResult = expect.arrayContaining([
            expect.objectContaining({ name: 'Foo' }),
            expect.objectContaining({ name: 'Bar' }),
            expect.objectContaining({ name: 'Baz' })
        ]);

        expect(wrapper.vm.associationEntities).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: expect.any(String),
                    detailRoute: expect.any(String),
                    repository: expect.any(Object),
                    gridColumns: expect.any(Array),
                    criteria: expect.any(Function),
                    loadedData: expectedEntityCollectionResult // Expect loaded data
                })
            ])
        );
    });

    it('should render an entity-listing for each entity when all entities have results', async () => {
        const wrapper = createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'event_action'
        ]);

        // Wait for repository request
        await wrapper.vm.$nextTick();

        // Wait for loading to be disabled and re-render
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Expect entity listings to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-payment_method .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-event_action .sw-entity-listing-stub').exists()).toBeTruthy();

        // Loader and empty-state should not be present
        expect(wrapper.find('sw-settings-rule-detail-assignments__empty-state').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render an entity-listing only for entities which return a result', async () => {
        const wrapper = createWrapper([
            'shipping_method',
            'payment_method',
            'promotion'
        ]);

        // Wait for repository request
        await wrapper.vm.$nextTick();

        // Wait for loading to be disabled and re-render
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // Expect entity listings to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-payment_method .sw-entity-listing-stub').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion .sw-entity-listing-stub').exists()).toBeTruthy();

        // Expect entity listings to be not present for entities without result
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .sw-entity-listing-stub').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-event_action .sw-entity-listing-stub').exists()).toBeFalsy();

        // Loader and empty-state should not be present
        expect(wrapper.find('sw-settings-rule-detail-assignments__empty-state').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render an empty-state when none of the associated entities returns a result', async () => {
        const wrapper = createWrapper();

        // Wait for repository request
        await wrapper.vm.$nextTick();

        // Wait for loading to be disabled and re-render
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-settings-rule-detail-assignments__empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render names of product variants', async () => {
        const wrapper = await createWrapper(['product']);

        // Wait for repository request
        await wrapper.vm.$nextTick();

        // Wait for loading to be disabled and re-render
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // expect entity listing for products to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .sw-entity-listing-stub').exists()).toBeTruthy();

        const productAssignments = wrapper.findAll('.sw-settings-rule-detail-assignments__entity-listing-product li');

        // expect the right amount of items
        expect(productAssignments.length).toBe(4);

        const validNames = ['Foo', 'Bar', 'Baz', 'Inherited'];

        // expect the correct names of the products
        productAssignments.wrappers.forEach((assignment, index) => {
            expect(assignment.text()).toBe(validNames[index]);
        });
    });

    it('should have the right link inside the template', async () => {
        const wrapper = createWrapper([
            'promotion'
        ]);

        // Wait for repository request
        await wrapper.vm.$nextTick();

        // Wait for loading to be disabled and re-render
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        const promotionListing = wrapper.find('.sw-settings-rule-detail-assignments__entity-listing-promotion');

        // expect promotion entity listing to exist
        expect(promotionListing.exists()).toBe(true);

        const detailRouteAttribute = promotionListing.attributes('detail-route');

        // expect detail-route attribute to be correct
        expect(detailRouteAttribute).toBe('sw.promotion.v2.detail.conditions');
    });
});
