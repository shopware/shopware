import { nextTick } from 'vue';
import { mount } from '@vue/test-utils';

/**
 * @sw-package after-sales
 */

const { EntityCollection, Criteria } = Shopware.Data;
const { Context } = Shopware;

function createRuleMock(isNew) {
    return {
        id: '1',
        name: 'Test rule',
        isNew: () => isNew,
        conditions: {
            entity: 'rule',
            source: 'foo/rule',
        },
    };
}

const ruleConditionDataProviderServiceMock = {
    getModuleTypes: () => [],
    addScriptConditions: () => {},
    getAwarenessConfigurationByAssignmentName: () => ({}),
    getDeprecationsInTree: jest.fn(() => []),
    getFlowOnlyTypesInTree: jest.fn(() => []),
};

const conditionRepositoryMock = {
    search: jest.fn(),
};

async function createWrapper(ruleId = null) {
    return mount(
        await wrapTestComponent('sw-flow-rule-modal', {
            sync: true,
        }),
        {
            props: {
                ruleId: ruleId,
            },
            global: {
                provide: {
                    repositoryFactory: {
                        create: (repository, source) => {
                            return {
                                create: () => {
                                    return createRuleMock(true);
                                },
                                get: () => Promise.resolve(createRuleMock(false)),
                                save: () => Promise.resolve(),
                                search: source === 'foo/rule' ? conditionRepositoryMock.search : () => Promise.resolve([]),
                            };
                        },
                    },
                    ruleConditionDataProviderService: ruleConditionDataProviderServiceMock,
                    ruleConditionsConfigApiService: {
                        load: () => Promise.resolve(),
                    },
                },

                stubs: {
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'sw-container': await wrapTestComponent('sw-container'),
                    'sw-multi-select': await wrapTestComponent('sw-multi-select'),
                    'sw-textarea-field': await wrapTestComponent('sw-textarea-field'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'mt-tabs': {
                        name: 'mt-tabs',
                        emits: ['new-item-active'],
                        props: {
                            defaultItem: {
                                type: String,
                                required: false,
                                default: undefined,
                            },
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: true,
                            },
                        },
                        template: '<div class="mt-tabs"></div>',
                    },
                    'sw-modal': {
                        template: `
                    <div class="sw-modal">
                      <slot name="modal-header"></slot>
                      <slot></slot>
                      <slot name="modal-footer"></slot>
                    </div>
                `,
                    },
                    'sw-button-process': {
                        template: '<button @click="$emit(\'click\', $event)"><slot></slot></button>',
                    },
                    'sw-condition-tree': true,
                    'mt-banner': true,
                    'sw-extension-component-section': true,
                    'router-link': true,
                    'sw-select-selection-list': true,
                    'sw-highlight-text': true,
                    'sw-select-result': true,
                    'sw-select-result-list': true,
                    'sw-select-base': true,
                    'sw-field-copyable': true,
                    'sw-contextual-field': true,
                    'sw-textarea-field-deprecated': true,
                },
            },
        },
    );
}

describe('module/sw-flow/component/sw-flow-rule-modal', () => {
    beforeEach(() => {
        ruleConditionDataProviderServiceMock.getDeprecationsInTree.mockReturnValue([]);
        conditionRepositoryMock.search.mockReset();
    });

    it('loads rule conditions in API-sized pages with stable sorting', async () => {
        const firstPage = Array.from({ length: 500 }, (_, index) => ({ id: `condition-${index}` }));
        const secondPage = [{ id: 'condition-500' }];

        conditionRepositoryMock.search
            .mockResolvedValueOnce(
                new EntityCollection(
                    'rule_condition',
                    'rule_condition',
                    { ...Context.api, inheritance: true },
                    new Criteria(1),
                    firstPage,
                    501,
                ),
            )
            .mockResolvedValueOnce(
                new EntityCollection(
                    'rule_condition',
                    'rule_condition',
                    { ...Context.api, inheritance: true },
                    new Criteria(2),
                    secondPage,
                    501,
                ),
            );

        const wrapper = await createWrapper('1');
        await flushPromises();

        expect(conditionRepositoryMock.search).toHaveBeenCalledTimes(2);

        const firstCriteria = new Criteria(1);
        firstCriteria.addSorting(Criteria.sort('parentId'));
        firstCriteria.addSorting(Criteria.sort('position'));
        firstCriteria.addSorting(Criteria.sort('id'));

        const secondCriteria = new Criteria(2);
        secondCriteria.addSorting(Criteria.sort('parentId'));
        secondCriteria.addSorting(Criteria.sort('position'));
        secondCriteria.addSorting(Criteria.sort('id'));

        expect(conditionRepositoryMock.search.mock.calls[0][0]).toEqual(firstCriteria);
        expect(conditionRepositoryMock.search.mock.calls[1][0]).toEqual(secondCriteria);
        expect(conditionRepositoryMock.search.mock.calls[0][1]).toMatchObject({
            inheritance: true,
        });
        expect(conditionRepositoryMock.search.mock.calls[1][1]).toMatchObject({
            inheritance: true,
        });
        expect(wrapper.vm.conditions).toHaveLength(501);
    });

    // @deprecated tag:v6.8.0 - The test will be removed with the legacy flow-rule tabs.
    it.deprecated('v6.8.0.0')('should show element correctly in the fallback tab branch', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);

        const conditionElement = wrapper.find('.sw-flow-rule-modal__tab-rule');
        expect(conditionElement.exists()).toBe(true);

        const fieldClasses = [
            '.sw-flow-rule-modal__name',
            '.sw-flow-rule-modal__priority',
            '.sw-flow-rule-modal__description',
            '.sw-flow-rule-modal__type',
        ];

        const detailHeaderTab = wrapper.find('.sw-flow-rule-modal__tab-detail');
        await detailHeaderTab.trigger('click');
        await flushPromises();

        fieldClasses.forEach((elementClass) => {
            expect(wrapper.find(elementClass).exists()).toBe(true);
        });
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should render meteor tabs', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-flow-rule-modal');
        expect(tabs.props('defaultItem')).toBe('detail');
        expect(tabs.props('items')).toEqual([
            {
                label: 'sw-flow.modals.rule.tabDetail',
                name: 'detail',
            },
            {
                label: 'sw-flow.modals.rule.tabRule',
                name: 'rule',
            },
        ]);
        expect(wrapper.find('.sw-tabs').exists()).toBe(false);
        expect(wrapper.find('.sw-flow-rule-modal__name').exists()).toBe(true);
    });

    it.activeFeatureFlags(['v6.8.0.0'])('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        await tabs.vm.$emit('new-item-active', 'rule');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.activeTab).toBe('rule');
        expect(wrapper.find('.sw-flow-rule-modal__name').exists()).toBe(false);
        expect(wrapper.find('.sw-flow-rule-modal__rule').exists()).toBe(true);
    });

    it('should emit event process-finish when saving rule successfully', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-flow-rule-modal__save-button');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.emitted()['process-finish']).toBeTruthy();
    });

    it('should show deprecation warning when legacy product states condition exists', async () => {
        ruleConditionDataProviderServiceMock.getDeprecationsInTree.mockReturnValue([
            {
                type: 'cartLineItemProductStates',
                label: 'Cart line item product states',
                version: 'v6.8.0',
                replacement: {
                    type: 'cartLineItemProductType',
                    label: 'Cart line item product type',
                },
            },
        ]);

        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.conditions = [
            {
                type: 'cartLineItemProductStates',
            },
        ];
        await nextTick();
        await flushPromises();

        const banner = wrapper.find('.sw-flow-rule-modal__product-type-warning');

        expect(banner.exists()).toBe(true);
        expect(banner.attributes('variant')).toBe('attention');
    });
});
