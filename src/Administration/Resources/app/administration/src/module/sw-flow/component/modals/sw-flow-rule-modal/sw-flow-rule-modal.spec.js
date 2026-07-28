import { mount } from '@vue/test-utils';

/**
 * @sw-package after-sales
 */

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

async function createWrapper({ featureActive = false } = {}) {
    return mount(
        await wrapTestComponent('sw-flow-rule-modal', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    repositoryFactory: {
                        create: () => {
                            return {
                                create: () => {
                                    return createRuleMock(true);
                                },
                                get: () => Promise.resolve(createRuleMock(false)),
                                save: () => Promise.resolve(),
                                search: () => Promise.resolve([]),
                            };
                        },
                    },
                    ruleConditionDataProviderService: ruleConditionDataProviderServiceMock,
                    ruleConditionsConfigApiService: {
                        load: () => Promise.resolve(),
                    },

                    feature: {
                        isActive: (feature) => {
                            if (feature === 'v6.8.0.0') {
                                return featureActive;
                            }

                            return false;
                        },
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
        global.activeFeatureFlags = [];
        ruleConditionDataProviderServiceMock.getDeprecationsInTree.mockReturnValue([]);
    });

    it('should show element correctly in the fallback tab branch', async () => {
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

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({ featureActive: true });
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

    it('should switch meteor tab content when the active tab changes', async () => {
        const wrapper = await createWrapper({ featureActive: true });
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

        await wrapper.setData({
            conditions: [
                {
                    type: 'cartLineItemProductStates',
                },
            ],
        });
        await flushPromises();

        const banner = wrapper.find('.sw-flow-rule-modal__product-type-warning');

        expect(banner.exists()).toBe(true);
        expect(banner.attributes('variant')).toBe('attention');
    });
});
