import { shallowMount, enableAutoDestroy, createLocalVue } from '@vue/test-utils';
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

function createWrapper(privileges = []) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-detail'), {
        localVue,
        provide: { repositoryFactory: {
            create: () => ({
                create: () => {
                    return Promise.resolve({});
                },
                save: () => {
                    return Promise.resolve();
                },
                get: (id) => {
                    return Promise.resolve(
                        {
                            id,
                            name: 'Flow 1',
                            eventName: 'checkout.customer'
                        }
                    );
                }
            })
        },

        acl: {
            can: (identifier) => {
                if (!identifier) {
                    return true;
                }

                return privileges.includes(identifier);
            }
        } },

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
            }
        }
    });
}

enableAutoDestroy(afterEach);

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

    it('should not be able to save a flow', () => {
        const wrapper = createWrapper();

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to save a flow ', () => {
        const wrapper = createWrapper([
            'flow.editor'
        ]);

        const saveButton = wrapper.find('.sw-flow-detail__save');
        expect(saveButton.attributes().disabled).toBeFalsy();
    });

    it('should able to remove selector sequences before saving', async () => {
        const wrapper = createWrapper([
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

    it('should able to validate sequences before saving', async () => {
        const wrapper = createWrapper([
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
});
