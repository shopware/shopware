/* eslint-disable max-len */
import { mount } from '@vue/test-utils_v3';
import EntityCollection from 'src/core/data/entity-collection.data';

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

async function createWrapper(entitiesWithResults = [], customProps = {}) {
    return mount(await wrapTestComponent('sw-settings-rule-detail-assignments', { sync: true }), {
        props: {
            ruleId: 'uuid1',
            rule: {
                name: 'Test rule',
                priority: 7,
                description: 'Lorem ipsum',
                type: '',
            },
            ...customProps,
        },
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot name="toolbar"></slot><slot name="grid"></slot></div>',
                },
                'sw-loader': true,
                'sw-empty-state': true,
                'sw-settings-rule-assignment-listing': await wrapTestComponent('sw-settings-rule-assignment-listing'),
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-pagination': true,
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-checkbox-field': true,
                'sw-context-menu-item': true,
                'sw-icon': true,
                'sw-button': true,
                'sw-field-error': true,
                'sw-card-filter': true,
                'router-link': {
                    template: '<a class="router-link" :detail-route="to.name"><slot></slot></a>',
                    props: ['to'],
                },
                'sw-alert': true,
            },
            provide: {
                validationService: {},
                shortcutService: {
                    startEventListener: () => {
                    },
                    stopEventListener: () => {
                    },
                },

                repositoryFactory: {
                    create: (entityName) => {
                        return {
                            search: (_, api) => {
                                const entities = [
                                    { name: 'Foo' },
                                    { name: 'Bar' },
                                    { name: 'Baz' },
                                ];

                                if (api.inheritance) {
                                    entities.push({ name: 'Inherited' });
                                }

                                if (entitiesWithResults.includes(entityName)) {
                                    return Promise.resolve(createEntityCollectionMock(entityName, entities));
                                }

                                return Promise.resolve(createEntityCollectionMock(entityName));
                            },
                        };
                    },
                },

                ruleConditionDataProviderService: {
                    getRestrictedAssociations: () => {
                    },
                    getTranslatedConditionViolationList: () => {
                        return 'text';
                    },
                    isRuleRestricted: () => {
                        return true;
                    },
                    getRestrictedRuleTooltipConfig: () => ({ message: 'tooltipConfig', disabled: true }),
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-assignments', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should prepare association entities list', async () => {
        const wrapper = await createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'flow',
        ]);

        expect(wrapper.vm.associationEntities).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    allowAdd: expect.any(Boolean),
                    api: expect.any(Function),
                    associationName: expect.any(String),
                    criteria: expect.any(Function),
                    detailRoute: expect.any(String),
                    entityName: expect.any(String),
                    gridColumns: expect.any(Array),
                    loadedData: expect.any(Array),
                }),
            ]),
        );
    });

    it('should try to load and assign entity data for defined entities', async () => {
        const wrapper = await createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'flow',
        ]);
        await flushPromises();

        const expectedEntityCollectionResult = expect.arrayContaining([
            expect.objectContaining({ name: 'Foo' }),
            expect.objectContaining({ name: 'Bar' }),
            expect.objectContaining({ name: 'Baz' }),
        ]);

        expect(wrapper.vm.associationEntities).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    entityName: expect.any(String),
                    detailRoute: expect.any(String),
                    repository: expect.any(Object),
                    gridColumns: expect.any(Array),
                    criteria: expect.any(Function),
                    loadedData: expectedEntityCollectionResult, // Expect loaded data
                }),
            ]),
        );
    });

    it('should render an entity-listing for each entity when all entities have results', async () => {
        const wrapper = await createWrapper([
            'product',
            'shipping_method',
            'payment_method',
            'promotion',
            'flow',
        ]);
        await flushPromises();

        // Expect entity listings to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method_prices .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-payment_method .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_order_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_discount_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_group_rule .router-link').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-flow .router-link').exists()).toBeTruthy();

        // Empty states should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-product').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_availability_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_prices').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_order_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_customer_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_cart_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_discount_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_group_rule').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-flow').exists()).toBeFalsy();

        // Loader should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render an entity-listing also if no assignment is found', async () => {
        const wrapper = await createWrapper([]);
        await flushPromises();

        // Expect entity listings to not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method_availability_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-shipping_method_prices .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-payment_method .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_order_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_customer_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_cart_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_discount_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-promotion_group_rule .router-link').exists()).toBeFalsy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-flow .router-link').exists()).toBeFalsy();

        // Expect empty states to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-product').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_availability_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-shipping_method_prices').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-payment_method').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_order_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_customer_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_cart_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_discount_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-promotion_group_rule').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-flow').exists()).toBeTruthy();

        // Loader should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render an empty-state when none of the associated entities returns a result', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render names of product variants', async () => {
        const wrapper = await await createWrapper(['product']);
        await flushPromises();

        // expect entity listing for products to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .router-link').exists()).toBeTruthy();

        const productAssignments = wrapper.findAll('.sw-settings-rule-detail-assignments__entity-listing-product .sw-data-grid__cell--name');

        // expect the right amount of items
        expect(productAssignments).toHaveLength(4);

        const validNames = ['Foo', 'Bar', 'Baz', 'Inherited'];

        // expect the correct names of the products
        productAssignments.forEach((assignment, index) => {
            expect(assignment.text()).toBe(validNames[index]);
        });
    });

    it('should have the right link inside the template', async () => {
        const wrapper = await createWrapper([
            'promotion',
        ]);
        await flushPromises();

        const promotionListing = wrapper.find('.sw-settings-rule-detail-assignments__entity-listing-promotion_order_rule .sw-data-grid__cell--name  .router-link');

        // expect promotion entity listing to exist
        expect(promotionListing.exists()).toBe(true);


        const detailRouteAttribute = promotionListing.attributes('detail-route');

        // expect detail-route attribute to be correct
        expect(detailRouteAttribute).toBe('sw.promotion.v2.detail.conditions');
    });

    it('should disable adding then rule is restricted', async () => {
        const wrapper = await createWrapper();
        const disabled = wrapper.vm.disableAdd({});

        expect(disabled).toBeTruthy();
    });

    it('should call rule condition service', async () => {
        const wrapper = await createWrapper();
        const config = wrapper.vm.getTooltipConfig({});

        expect(config.message).toBe('tooltipConfig');
    });
});
