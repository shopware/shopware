import { shallowMount, createLocalVue } from '@vue/test-utils';
import swFlowDetail from 'src/module/sw-flow/page/sw-flow-detail';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-flow-detail', swFlowDetail);

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
    saveSuccess = true,
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
                    return saveSuccess ? Promise.resolve() : Promise.reject();
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
            $route: { params: param, query: query }
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
            'sw-alert': true,
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

        Shopware.Service().register('flowBuilderService', () => {
            return {
                rearrangeArrayObjects: (sequences) => {
                    return sequences;
                }
            };
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

        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection(sequencesFixture)
            }
        );

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
        }, null, false);

        Shopware.State.commit('swFlowState/setFlow', {
            name: 'Flow 1',
            config: {
                eventName: 'checkout.customer',
                sequences: getSequencesCollection(sequencesFixture)
            }
        });

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(4);

        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toBeCalled();
    });

    it('should able to validate sequences before saving', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ]);

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection([{
                    ...sequenceFixture,
                    ruleId: ''
                }])
            }
        );

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
        }, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE
        });

        await flushPromises();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState.length).toEqual(1);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBe(undefined);
    });

    it('should be able to build sequence collection from config of flow template', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        const sequences = [
            { id: 'd2b3a82c22284566b6a56fb47d577bfd', parentId: null },
            { id: '900a915617054a5b8acbfda1a35831fa', parentId: 'd2b3a82c22284566b6a56fb47d577bfd' },
            { id: 'f1beccf9c40244e6ace2726d2afc476c', parentId: '900a915617054a5b8acbfda1a35831fa' },
        ];

        jest.spyOn(Shopware.Utils, 'createId')
            .mockReturnValueOnce('d2b3a82c22284566b6a56fb47d577bfd_new')
            .mockReturnValueOnce('900a915617054a5b8acbfda1a35831fa_new')
            .mockReturnValueOnce('f1beccf9c40244e6ace2726d2afc476c_new');

        expect(JSON.stringify(wrapper.vm.buildSequencesFromConfig(sequences))).toEqual(JSON.stringify(getSequencesCollection([
            { id: 'd2b3a82c22284566b6a56fb47d577bfd_new', parentId: null },
            { id: '900a915617054a5b8acbfda1a35831fa_new', parentId: 'd2b3a82c22284566b6a56fb47d577bfd_new' },
            { id: 'f1beccf9c40244e6ace2726d2afc476c_new', parentId: '900a915617054a5b8acbfda1a35831fa_new' },
        ])));
    });

    it('should be show the warning message not able to edited flow template', async () => {
        const wrapper = await createWrapper([
            'flow.editor'
        ], {
            type: 'template'
        }, {}, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE
        });

        const alertElement = wrapper.findAll('.sw-flow-detail__template');
        expect(alertElement.exists()).toBe(true);
    });
});
