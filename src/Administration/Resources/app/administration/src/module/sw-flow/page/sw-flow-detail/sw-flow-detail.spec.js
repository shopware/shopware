import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/module/sw-flow/page/sw-flow-detail';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    config: {}
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        ruleId: '1111'
    },
    {
        ...sequenceFixture,
        parentId: '1',
        id: '2',
        trueCase: true
    },
    {
        ...sequenceFixture,
        actionName: 'sendMail',
        parentId: '1',
        id: '3',
        trueCase: false
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '4'
    }
];

const ID_FLOW = '4006d6aa64ce409692ac2b952fa56ade';
const ID_FLOW_TEMPLATE = '0e6b005ca7a1440b8e87ac3d45ed5c9f';

function getSequencesCollection(collection = []) {
    return new EntityCollection(
        '/flow_sequence',
        'flow_sequence',
        null,
        { isShopwareContext: true },
        collection,
        collection.length,
        null
    );
}

async function createWrapper(
    privileges = [],
    query = {},
    config = {},
    flowId = null,
    promise = Promise.resolve(),
    param = {}
) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-detail'), {
        localVue,
        provide: { repositoryFactory: {
            create: () => ({
                create: () => {
                    return {};
                },
                save: () => {
                    return promise;
                },
                get: (id) => {
                    if (id === ID_FLOW) {
                        return Promise.resolve(
                            {
                                id,
                                name: 'Flow 1',
                                eventName: 'checkout.customer',
                                ...config
                            }
                        );
                    }

                    return Promise.resolve(
                        {
                            id,
                            name: 'Flow template 1',
                            config: config
                        }
                    );
                }
            })
        },

        ruleConditionDataProviderService: {
            getRestrictedRules: () => Promise.resolve([])
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            }
        } },

        mocks: {
            $route: { params: param, query: query },
        },

        propsData: {
            flowId: flowId
        },

        stubs: {
            'sw-page': {
                template: `
                    <div class="sw-page">
                        <slot name="search-bar"></slot>
                        <slot name="smart-bar-back"></slot>
                        <slot name="smart-bar-header"></slot>
                        <slot name="language-switch"></slot>
                        <slot name="smart-bar-actions"></slot>
                        <slot name="side-content"></slot>
                        <slot name="content"></slot>
                        <slot name="sidebar"></slot>
                        <slot></slot>
                    </div>
                `
            },
            'sw-button': true,
            'sw-card-view': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
            'router-view': true,
            'sw-button-process': {
                template: `
                    <button class="sw-button-process" v-bind="$attrs" v-on="$listeners">
                        <slot></slot>
                    </button>
                `
            },
            'sw-skeleton': true,
        }
    });
}

describe('module/sw-flow/page/sw-flow-detail', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }])
                },
                invalidSequences: []
            }
        });
    });

    it('should not be able to save a flow', async () => {
        const wrapper = await createWrapper();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save a flow ', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ], {}, {}, ID_FLOW);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should able to remove selector sequences before saving', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ]);

        Shopware.State.commit('swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture)
            });

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(4);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(2);
    });

    it('should not able to saving flow', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ], {}, {
            eventName: 'checkout.customer',
            sequences: [1, 2]
        }, null, Promise.reject());

        Shopware.State.commit('swFlowState/setFlow',
            {
                name: 'Flow 1',
                config: {
                    eventName: 'checkout.customer',
                    sequences: getSequencesCollection(sequencesFixture)
                }
            });

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(4);

        await wrapper.vm.$nextTick();
        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.createNotificationError).toBeCalled();
    });

    it('should able to validate sequences before saving', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.State.commit('swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection([{
                    ...sequenceFixture,
                    ruleId: ''
                }])
            });

        let invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual([]);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');

        invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual(['1']);

        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();
        wrapper.vm.createNotificationWarning.mockRestore();
    });

    it('should able to create flow from flow template', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ], {}, {
            eventName: 'checkout.customer',
            sequences: [{
                id: 'sequence-id',
                config: {}
            }]
        }, null, Promise.resolve(), {
            flowTemplateId: ID_FLOW_TEMPLATE
        });

        await wrapper.vm.$nextTick();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(1);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });
});
