import { mount } from '@vue/test-utils';

import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';

/**
 * @package services-settings
 * @group disabledCompat
 */

async function createWrapper() {
    return mount(await wrapTestComponent('sw-flow-sequence-action-error', {
        sync: true,
    }), {
        props: {
            sequence: {
                id: '1',
                actionName: null,
                ruleId: '1111',
                parentId: null,
                position: 1,
                displayGroup: 1,
            },
        },
        global: {
            stubs: {
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-icon': true,
                'sw-context-menu': {
                    template: '<div><slot></slot></div>',
                },
                'sw-popover': {
                    template: '<div><slot></slot></div>',
                },
                'router-link': true,
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

    it('should able to show the error content', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const content = wrapper.find('.sw-flow-sequence-action-error__content');

        expect(content.exists()).toBeTruthy();
    });

    it('should able to delete action', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const contextButton = wrapper.find('.sw-context-button');
        await contextButton.trigger('click');
        await flushPromises();

        const button = wrapper.find('.sw-flow-sequence-action-error__delete-action');
        await button.trigger('click');
        await flushPromises();

        const sequencesState = await Shopware.State.getters['swFlowState/sequences'];

        expect(sequencesState).toHaveLength(0);
    });
});
