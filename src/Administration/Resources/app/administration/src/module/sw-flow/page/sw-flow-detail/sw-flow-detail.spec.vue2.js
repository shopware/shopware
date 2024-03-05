import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';
import FlowBuilderService from 'src/module/sw-flow/service/flow-builder.service';

Shopware.Service().register('flowBuilderService', () => {
    return {
        ...new FlowBuilderService(),
        rearrangeArrayObjects: (sequences) => {
            return sequences;
        },
    };
});

const sequenceFixture = {
    id: '1',
    actionName: null,
    ruleId: null,
    parentId: null,
    position: 1,
    displayGroup: 1,
    config: {},
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        ruleId: '1111',
    },
    {
        ...sequenceFixture,
        parentId: '1',
        id: '2',
        trueCase: true,
    },
    {
        ...sequenceFixture,
        actionName: 'sendMail',
        parentId: '1',
        id: '3',
        trueCase: false,
    },
    {
        ...sequenceFixture,
        displayGroup: 2,
        position: 2,
        id: '4',
    },
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
        null,
    );
}

const mockBusinessEvents = [
    {
        name: 'checkout.customer.before.login',
        mailAware: true,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
    {
        name: 'checkout.customer.changed-payment-method',
        mailAware: false,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
    {
        name: 'checkout.customer.deleted',
        mailAware: true,
        aware: ['Shopware\\Core\\Framework\\Event\\SalesChannelAware'],
    },
];

async function createWrapper(
    query = {},
    config = {},
    flowId = null,
    saveSuccess = true,
    param = {},
) {
    return mount(await wrapTestComponent('sw-flow-detail', {
        sync: true,
    }), {
        props: {
            flowId: flowId,
        },
        global: {
            provide: {
                repositoryFactory: {
                    create: (entity) => {
                        if (entity === 'flow_sequence') {
                            return {
                                sync: jest.fn((sequences) => {
                                    expect(sequences).toHaveLength(2);

                                    const ids = [];
                                    sequences.forEach((sequence) => {
                                        ids.push(sequence.id);
                                    });

                                    expect(ids).toEqual(['1', '3']);
                                }),
                                syncDeleted: jest.fn((sequencesIds) => {
                                    const ids = [];
                                    sequencesIds.forEach((sequenceId) => {
                                        ids.push(sequenceId);
                                    });

                                    expect(ids).toEqual(['2', '4']);
                                }),
                                create: () => {
                                    return {};
                                },
                            };
                        }

                        return {
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
                                            config,
                                        },
                                    );
                                }

                                return Promise.resolve(
                                    {
                                        id,
                                        name: 'Flow template 1',
                                        config,
                                    },
                                );
                            },
                            search: () => {
                                if (entity === 'rule') {
                                    return Promise.resolve([
                                        { id: '1111', name: 'test rule' },
                                    ]);
                                }

                                return Promise.resolve([]);
                            },
                            sync: () => {
                                return Promise.resolve();
                            },
                            syncDeleted: () => {
                                return Promise.resolve();
                            },
                        };
                    },
                },
                flowBuilderService: Shopware.Service('flowBuilderService'),
                ruleConditionDataProviderService: {
                    getRestrictedRules: () => Promise.resolve([]),
                },
            },
            mocks: {
                $route: { params: param, query: query },
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
                    `,
                },
                'sw-tabs-item': await wrapTestComponent('sw-tabs-item', { sync: true }),
                'router-view': true,
                'sw-button-process': await wrapTestComponent('sw-button-process', { sync: true }),
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
                'sw-skeleton': true,
                'sw-alert': true,
            },
        },
    });
}

describe('module/sw-flow/page/sw-flow-detail', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }]),
                },
                invalidSequences: [],
                appActions: [],
                triggerEvents: [],
            },
        });

        Shopware.Service().register('businessEventService', () => {
            return {
                getBusinessEvents: () => Promise.resolve(mockBusinessEvents),
            };
        });
    });

    it('should not be able to save a flow', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();
        await flushPromises();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBe('');
    });

    it('should be able to save a flow', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper({}, {}, ID_FLOW);
        await flushPromises();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should be able to remove selector sequences before saving a newly created flow', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        const flow = {
            eventName: 'checkout.customer',
            name: 'Flow 1',
            sequences: getSequencesCollection(sequencesFixture),
            isNew: () => true,
        };

        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                ...flow,
                getOrigin: () => flow,
            },
        );

        let sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(4);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');

        sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(2);
    });

    it('should be able to update sequences before saving exist flow', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        const flow = {
            eventName: 'checkout.customer',
            name: 'Flow 1',
            sequences: getSequencesCollection(sequencesFixture),
        };

        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                ...flow,
                getOrigin: () => flow,
            },
        );

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(4);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.flowSequenceRepository.syncDeleted).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.flowSequenceRepository.sync).toHaveBeenCalledTimes(1);
    });

    it('should not able to saving flow template', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper({
            type: 'template',
        }, {}, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE,
        });

        const flowTemplate = {
            name: 'Flow template',
            config: {
                eventName: 'checkout.customer',
                sequences: getSequencesCollection(sequencesFixture),
            },
        };

        Shopware.State.commit('swFlowState/setFlow', {
            ...flowTemplate,
            getOrigin: () => flowTemplate,
        });

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(4);

        await flushPromises();

        wrapper.vm.createNotificationError = jest.fn();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        expect(wrapper.vm.createNotificationError).toHaveBeenCalled();
    });

    it('should able to validate sequences before saving', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper();
        await flushPromises();

        wrapper.vm.createNotificationWarning = jest.fn();

        Shopware.State.commit(
            'swFlowState/setFlow',
            {
                eventName: 'checkout.customer',
                name: 'Flow 1',
                sequences: getSequencesCollection([{
                    ...sequenceFixture,
                    ruleId: '',
                }]),
            },
        );

        let invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual([]);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        await saveButton.trigger('click');
        await flushPromises();

        invalidSequences = Shopware.State.get('swFlowState').invalidSequences;
        expect(invalidSequences).toEqual(['1']);

        expect(wrapper.vm.createNotificationWarning).toHaveBeenCalled();
        wrapper.vm.createNotificationWarning.mockRestore();
    });

    it('should set route for card tabs when creating a new flow', async () => {
        global.activeAclRoles = ['flow.editor'];

        const wrapper = await createWrapper({}, {
            eventName: 'checkout.customer',
            sequences: [{
                id: 'sequence-id',
                config: {},
            }],
        });
        await flushPromises();

        const tabs = {
            general: wrapper.findComponent('.sw-flow-detail__tab-general'),
            flow: wrapper.findComponent('.sw-flow-detail__tab-flow'),
        };

        expect(tabs.general.vm.route).toStrictEqual({
            name: 'sw.flow.create.general',
        });

        expect(tabs.flow.vm.route).toStrictEqual({
            name: 'sw.flow.create.flow',
        });
    });

    it('should be able to create flow from flow template', async () => {
        global.activeAclRoles = ['flow.editor'];

        const wrapper = await createWrapper({}, {
            eventName: 'checkout.customer',
            sequences: [{
                id: 'sequence-id',
                config: {},
            }],
        }, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE,
        });

        await flushPromises();

        const sequencesState = Shopware.State.getters['swFlowState/sequences'];
        expect(sequencesState).toHaveLength(1);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeUndefined();
    });

    it('should set flowTemplateId in route for card tabs when creating flow from flow template', async () => {
        global.activeAclRoles = ['flow.editor'];

        const wrapper = await createWrapper({}, {
            eventName: 'checkout.customer',
            sequences: [{
                id: 'sequence-id',
                config: {},
            }],
        }, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE,
        });
        await flushPromises();

        const tabs = {
            general: wrapper.findComponent('.sw-flow-detail__tab-general'),
            flow: wrapper.findComponent('.sw-flow-detail__tab-flow'),
        };

        expect(tabs.general.vm.route).toStrictEqual({
            name: 'sw.flow.create.general',
            params: { flowTemplateId: ID_FLOW_TEMPLATE },
        });

        expect(tabs.flow.vm.route).toStrictEqual({
            name: 'sw.flow.create.flow',
            params: { flowTemplateId: ID_FLOW_TEMPLATE },
        });
    });

    it('should be able to build sequence collection from config of flow template', async () => {
        global.activeAclRoles = [];
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

    it('should be able to show the warning message when editing flow template', async () => {
        global.activeAclRoles = ['flow.editor'];
        const wrapper = await createWrapper({
            type: 'template',
        }, {}, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE,
        });
        await flushPromises();

        const alertElement = wrapper.find('.sw-flow-detail__template');
        expect(alertElement.exists()).toBe(true);
    });

    it('should be able to get rule data for flow template', async () => {
        global.activeAclRoles = ['flow.editor'];

        const wrapper = await createWrapper({
            type: 'template',
        }, {}, null, true, {
            flowTemplateId: ID_FLOW_TEMPLATE,
        });

        Shopware.State.commit('swFlowState/setSequences', getSequencesCollection(sequencesFixture));

        await wrapper.vm.getRuleDataForFlowTemplate();
        await flushPromises();

        const sequences = Shopware.State.getters['swFlowState/sequences'];
        expect(sequences).toHaveLength(4);
        expect(sequences[0]).toHaveProperty('rule');
        expect(sequences[0].rule).toEqual({ id: '1111', name: 'test rule' });
    });
});
