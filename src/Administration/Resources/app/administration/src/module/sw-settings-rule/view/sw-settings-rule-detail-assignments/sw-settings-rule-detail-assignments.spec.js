import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';
import RuleAssignmentConfigurationService from 'src/module/sw-settings-rule/service/rule-assignment-configuration.service';

/**
 * @package services-settings
 */

const { Criteria } = Shopware.Data;
const { Context } = Shopware;

jest.mock('src/module/sw-settings-rule/service/rule-assignment-configuration.service');

const defaultProps = {
    ruleId: 'uuid1',
    rule: {
        id: 'uuid1',
        name: 'Test rule',
        priority: 7,
        description: 'Lorem ipsum',
        type: '',
    },
};

const testConfig = {
    product: {
        id: 'product',
        associationName: 'productPrices',
        notAssignedDataTotal: 1,
        allowAdd: true,
        entityName: 'product',
        label: 'sw-settings-rule.detail.associations.products',
        criteria: () => {
            const criteria = new Criteria(1, 5);
            criteria.addFilter(Criteria.equals('prices.rule.id', defaultProps.ruleId));
            criteria.addAssociation('options.group');

            return criteria;
        },
        api: () => {
            const api = { ...Context.api };
            api.inheritance = true;

            return api;
        },
        detailRoute: 'sw.product.detail.prices',
        addContext: {
            type: 'test',
        },
        gridColumns: [
            {
                property: 'name',
                label: 'Name',
                rawData: true,
                sortable: true,
                routerLink: 'sw.product.detail.prices',
                allowEdit: false,
            },
        ],
    },
};

const ruleAssignmentServiceMock = {
    getConfiguration: jest.fn(() => {
        return testConfig;
    }),
};

RuleAssignmentConfigurationService.mockImplementation(() => {
    return ruleAssignmentServiceMock;
});

function createEntityCollectionMock(entityName, items = []) {
    return new EntityCollection('/route', entityName, {}, {}, items, items.length);
}

function repositoryMock(entityName, entitiesWithResults) {
    return {
        search: jest.fn((_, api) => {
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
        }),
        sync: jest.fn(() => Promise.resolve()),
        save: jest.fn(() => Promise.resolve()),
    };
}

const ruleConditionDataProviderServiceMock = {
    getRestrictedAssociations: jest.fn(),
    getTranslatedConditionViolationList: () => {
        return 'text';
    },
    isRuleRestricted: jest.fn(() => {
        return false;
    }),
    getRestrictedRuleTooltipConfig: (_, association) => {
        const message = association ? 'has_association' : 'has_no_association';

        return { message, disabled: true };
    },
};

async function createWrapper(props = defaultProps, entitiesWithResults = ['product'], repositoryMockOverwrite = null, privileges = ['rule.editor']) {
    return mount(await wrapTestComponent('sw-settings-rule-detail-assignments', { sync: true }), {
        props,
        global: {
            stubs: {
                'sw-settings-rule-assignment-listing': await wrapTestComponent('sw-settings-rule-assignment-listing'),
                'sw-settings-rule-add-assignment-modal': await wrapTestComponent('sw-settings-rule-add-assignment-modal'),
                'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-entity-listing': await wrapTestComponent('sw-entity-listing'),
                'sw-data-grid': await wrapTestComponent('sw-data-grid'),
                'sw-simple-search-field': await wrapTestComponent('sw-simple-search-field'),
                'sw-text-field': await wrapTestComponent('sw-text-field'),
                'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-card-filter': await wrapTestComponent('sw-card-filter'),
                'sw-empty-state': await wrapTestComponent('sw-empty-state'),
                'router-link': {
                    template: '<a class="router-link" :detail-route="to.name"><slot></slot></a>',
                    props: ['to'],
                },
            },
            provide: {
                ruleConditionDataProviderService: ruleConditionDataProviderServiceMock,
                validationService: {},
                repositoryFactory: {
                    create: (entityName) => (repositoryMockOverwrite || repositoryMock(entityName, entitiesWithResults)),
                },
                acl: {
                    can: (identifier) => {
                        return privileges.includes(identifier);
                    },
                },
                shortcutService: {
                    startEventListener: jest.fn(),
                    stopEventListener: jest.fn(),
                },
            },
        },
    });
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-detail-assignments', () => {
    afterEach(() => {
        jest.clearAllMocks();
    });

    it('should prepare association entities list', async () => {
        const wrapper = await createWrapper();

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

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom api', defaultApi: false },
    ])('should load association data for defined entities: $name', async ({ defaultApi }) => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    api: defaultApi ? null : testConfig.product.api,
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const repository = wrapper.vm.associationEntities[0].repository;
        const expectedEntityCollectionResult = expect.arrayContaining([
            expect.objectContaining({ name: 'Foo' }),
            expect.objectContaining({ name: 'Bar' }),
            expect.objectContaining({ name: 'Baz' }),
        ]);

        expect(repository.search).toHaveBeenNthCalledWith(1, expect.any(Criteria), defaultApi ? Context.api : testConfig.product.api());
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

    it('should throw error if loading association data fails', async () => {
        const wrapper = await createWrapper(
            defaultProps,
            ['product'],
            {
                search: jest.fn(() => {
                    return Promise.reject(new Error('Error'));
                }),
            },
        );
        wrapper.vm.createNotificationError = jest.fn();
        await flushPromises();

        const repository = wrapper.vm.associationEntities[0].repository;
        expect(repository.search).toHaveBeenCalledTimes(1);

        expect(wrapper.vm.createNotificationError).toHaveBeenCalledWith({
            message: 'sw-settings-rule.detail.associationsLoadingError',
        });
    });

    it('should render an entity-listing for each entity when all entities have results', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        // Expect entity listings to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .router-link').exists()).toBe(true);

        // Empty states should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-product').exists()).toBe(false);

        // Loader should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBe(false);
    });

    it('should render an entity-listing also if no assignment is found', async () => {
        const wrapper = await createWrapper(defaultProps, []);
        await flushPromises();

        // Expect entity listings to not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-product .router-link').exists()).toBe(false);

        // Expect empty states to be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state-product img').exists()).toBe(true);

        // Loader should not be present
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBe(false);
    });

    it('should render an empty-state when none of the associated entities returns a result', async () => {
        const wrapper = await createWrapper(defaultProps, []);
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail-assignments__entity-empty-state').exists()).toBeTruthy();
        expect(wrapper.find('.sw-settings-rule-detail-assignments__card-loader').exists()).toBeFalsy();
    });

    it('should render names of product variants', async () => {
        const wrapper = await await createWrapper();
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
        const wrapper = await createWrapper();
        await flushPromises();

        const productListing = wrapper.find('.sw-settings-rule-detail-assignments__entity-listing-product .sw-data-grid__cell--name .router-link');

        // expect promotion entity listing to exist
        expect(productListing.exists()).toBe(true);
        const detailRouteAttribute = productListing.attributes('detail-route');

        // expect detail-route attribute to be correct
        expect(detailRouteAttribute).toBe(testConfig.product.detailRoute);
    });

    it.each([
        { name: 'not assigned total (true)', total: 0, restricted: false, disabled: true },
        { name: 'not assigned total (false)', total: 10, restricted: false, disabled: false },
        { name: 'restricted', total: 10, restricted: true, disabled: true },
    ])('should enable/disable rule add button: $name', async ({ total, restricted, disabled }) => {
        ruleConditionDataProviderServiceMock.isRuleRestricted.mockImplementationOnce(() => {
            return restricted;
        });
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    notAssignedDataTotal: total,
                },
            };
        });

        const wrapper = await createWrapper(
            defaultProps,
            total === 0 ? [] : ['product'],
        );
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-rule-detail-assignments__add-button');

        expect(addButton.exists()).toBe(true);
        expect(addButton.attributes('disabled') === '').toBe(disabled);
    });

    it.each([
        { name: 'has association', associationName: 'test' },
        { name: 'has no association', associationName: null },
    ])('should assign tooltip config to add button: $name', async ({ name, associationName }) => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    associationName,
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const addButton = wrapper.find('.sw-settings-rule-detail-assignments__add-button');

        expect(addButton.attributes('tooltip-mock-message')).toBe(name.replaceAll(' ', '_'));
        expect(addButton.attributes('tooltip-mock-disabled')).toBe('true');
    });

    it.each([
        { name: 'render', expected: true },
        { name: 'not render', expected: false },
    ])('should $name deletion', async ({ expected }) => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    ...(expected && {
                        deleteContext: {
                            test: 'test',
                        },
                    }),
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-context-button button').trigger('click');
        expect(wrapper.find('sw-context-menu-item[variant=danger]').exists()).toBe(expected);
    });

    it('should open/close delete modal', async () => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    deleteContext: {
                        test: 'test',
                    },
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-detail-assignments__delete-modal').exists()).toBe(false);

        await wrapper.find('.sw-data-grid__row--0 .sw-context-button button').trigger('click');
        expect(wrapper.find('sw-context-menu-item[variant=danger]').exists()).toBe(true);
        await wrapper.find('sw-context-menu-item[variant=danger]').trigger('click');

        expect(wrapper.find('.sw-settings-rule-detail-assignments__delete-modal').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail-assignments__delete-modal-cancel-button').trigger('click');

        expect(wrapper.find('.sw-settings-rule-detail-assignments__delete-modal').exists()).toBe(false);
    });

    it('should open/close add modal', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-modal').exists()).toBe(false);

        expect(wrapper.find('.sw-settings-rule-detail-assignments__add-button').attributes('disabled')).toBeUndefined();
        await wrapper.find('.sw-settings-rule-detail-assignments__add-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-modal').exists()).toBe(true);

        await wrapper.find('.sw-settings-rule-add-assignment-modal__cancel-button').trigger('click');
        expect(wrapper.find('.sw-settings-rule-add-assignment-modal').exists()).toBe(false);
    });

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom api', defaultApi: false },
    ])('should refresh assignment data after entities saved: $name', async ({ defaultApi }) => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    api: defaultApi ? null : testConfig.product.api,
                },
                flow: {
                    id: 'flow',
                    associationName: 'flowSequences',
                    criteria: () => new Criteria(1, 5),
                    gridColumns: testConfig.product.gridColumns,
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const repository = wrapper.vm.associationEntities[0].repository;

        expect(wrapper.find('.sw-settings-rule-detail-assignments__add-button').attributes('disabled')).toBeUndefined();
        await wrapper.find('.sw-settings-rule-detail-assignments__add-button').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-settings-rule-add-assignment-modal').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-add-assignment-modal__confirm-button').trigger('click');

        const assignedTotalCriteria = new Criteria(1, 1);
        assignedTotalCriteria.addFilter(Criteria.not('AND', testConfig.product.criteria().filters));

        const criterias = repository.search.mock.calls.map((call) => call[0]);
        const apis = repository.search.mock.calls.map((call) => call[1]);

        expect(criterias).toContainEqual(assignedTotalCriteria);
        expect(apis).toContainEqual(defaultApi ? Context.api : testConfig.product.api());
    });

    it('should not refresh assignment data when no context is given', async () => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    deleteContext: null,
                    addContext: null,
                },
            };
        });

        const wrapper = await createWrapper();
        await flushPromises();

        const repository = wrapper.vm.associationEntities[0].repository;

        expect(repository.search).toHaveBeenCalledTimes(1);
    });

    it.each([
        { name: 'default api', defaultApi: true, type: 'many-to-many' },
        { name: 'custom api', defaultApi: false, type: 'many-to-many' },
        { name: 'one-to-many', defaultApi: false, type: 'one-to-many' },
    ])('should delete item and refresh assignment data: $name', async ({ defaultApi, type }) => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    api: defaultApi ? null : testConfig.product.api,
                    deleteContext: {
                        type,
                        column: 'test',
                    },
                },
            };
        });

        const removeMock = jest.fn();

        const itemMock = {
            name: 'Foo',
            test: { remove: removeMock },
            getEntityName: () => 'product',
        };

        const repositoryOverwriteMock = {
            search: jest.fn(() => Promise.resolve(
                createEntityCollectionMock('product', [itemMock]),
            )),
            save: jest.fn(() => Promise.resolve()),
        };

        const wrapper = await createWrapper(
            defaultProps,
            ['product'],
            repositoryOverwriteMock,
        );
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-context-button button').trigger('click');
        expect(wrapper.find('sw-context-menu-item[variant=danger]').exists()).toBe(true);
        await wrapper.find('sw-context-menu-item[variant=danger]').trigger('click');

        expect(wrapper.find('.sw-settings-rule-detail-assignments__delete-modal').exists()).toBe(true);
        await wrapper.find('.sw-settings-rule-detail-assignments__delete-modal-delete-button').trigger('click');

        expect(removeMock).toHaveBeenCalledTimes(type === 'one-to-many' ? 0 : 1);

        expect(wrapper.find('.sw-entity-listing__confirm-bulk-delete-modal').exists()).toBe(false);

        expect(repositoryOverwriteMock.save).toHaveBeenNthCalledWith(
            1,
            // eslint-disable-next-line jest/no-conditional-expect
            type === 'one-to-many' ? { ...itemMock, test: null } : expect.any(Object),
            defaultApi ? Context.api : testConfig.product.api(),
        );
        expect(repositoryOverwriteMock.search).toHaveBeenCalledTimes(2);
    });

    it('should delete multiple items', async () => {
        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    deleteContext: {
                        column: 'test',
                    },
                },
            };
        });

        const itemMock = {
            name: 'Foo',
            test: { remove: jest.fn() },
            getEntityName: () => 'product',
        };

        const repositoryOverwriteMock = {
            search: jest.fn(() => Promise.resolve(
                createEntityCollectionMock('product', [itemMock]),
            )),
            save: jest.fn(() => Promise.resolve()),
        };

        const wrapper = await createWrapper(defaultProps, ['product'], repositoryOverwriteMock);
        await flushPromises();

        await wrapper.find('.sw-data-grid__row--0 .sw-field__checkbox input').setChecked(true);
        await wrapper.find('.sw-data-grid__row--0 .sw-field__checkbox input').trigger('click');

        await wrapper.find('.sw-settings-rule-detail-assignments__entity-listing .link-danger').trigger('click');

        expect(wrapper.find('.sw-entity-listing__confirm-bulk-delete-modal').exists()).toBe(true);
        await wrapper.find('.sw-entity-listing__confirm-bulk-delete-modal .sw-button--danger').trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-entity-listing__confirm-bulk-delete-modal').exists()).toBe(false);

        expect(repositoryOverwriteMock.save).toHaveBeenCalledTimes(1);
        expect(repositoryOverwriteMock.search).toHaveBeenCalledTimes(4);
    });

    it.each([
        { name: 'default api', defaultApi: true },
        { name: 'custom api', defaultApi: false },
    ])('should filter entities by search term: $name', async ({ defaultApi }) => {
        jest.useFakeTimers();

        ruleAssignmentServiceMock.getConfiguration.mockImplementationOnce(() => {
            return {
                ...testConfig,
                product: {
                    ...testConfig.product,
                    api: defaultApi ? null : testConfig.product.api,
                },
            };
        });

        const term = 'test';

        const wrapper = await createWrapper();
        await flushPromises();

        const repository = wrapper.vm.associationEntities[0].repository;
        repository.search.mockClear();

        await wrapper.find('.sw-simple-search-field input').setValue(term);
        await wrapper.find('.sw-simple-search-field input').trigger('update:value');

        jest.advanceTimersByTime(1000);
        await flushPromises();

        const criteria = testConfig.product.criteria();
        criteria.setPage(1);
        criteria.setTerm(term);

        expect(repository.search).toHaveBeenCalledWith(criteria, defaultApi ? Context.api : testConfig.product.api());

        jest.clearAllTimers();
    });

    it('should set router link', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-data-grid__row--0 .router-link').attributes('detail-route')).toBe(testConfig.product.detailRoute);
    });
});
