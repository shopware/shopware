import { createLocalVue, shallowMount } from '@vue/test-utils_v2';
import swFlowSequenceActionError from 'src/module/sw-flow/component/sw-flow-sequence-action-error';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu-item';

import Vuex from 'vuex_v2';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

Shopware.Component.register('sw-flow-sequence-action-error', swFlowSequenceActionError);

async function createWrapper() {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-sequence-action-error'), {
        localVue,
        stubs: {
            'sw-context-button': true,
            'sw-context-menu-item': await Shopware.Component.build('sw-context-menu-item'),
            'sw-icon': true,
        },
        propsData: {
            sequence: {
                id: '1',
                actionName: null,
                ruleId: '1111',
                parentId: null,
                position: 1,
                displayGroup: 1,
            },
        },
    });
}

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

const sequenceFixture = {
    id: '1',
    actionName: '',
    ruleId: null,
    parentId: '1',
    position: 1,
    displayGroup: 1,
    trueCase: false,
    config: {
        entity: 'Customer',
        tagIds: ['123'],
    },
};

describe('src/module/sw-flow/component/sw-flow-sequence-selector', () => {
    let wrapper;

    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                flow: {
                    eventName: '',
                    sequences: getSequencesCollection([{ ...sequenceFixture }]),
                },
            },
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    it('should able to show the error content', async () => {
        const content = wrapper.find('.sw-flow-sequence-action-error__content');
        expect(content.exists()).toBeTruthy();
    });

    it('should able to delete action', async () => {
        const button = wrapper.find('.sw-flow-sequence-action-error__delete-action');
        await button.trigger('click');

        const sequencesState = await Shopware.State.getters['swFlowState/sequences'];

        expect(sequencesState).toHaveLength(0);
    });
});
