import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-trigger';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/utils/sw-vnode-renderer';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';
import EntityCollection from 'src/core/data/entity-collection.data';
import { ACTION } from 'src/module/sw-flow/constant/flow.constant';

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

const sequenceFixture = {
    id: '2',
    actionName: '',
    ruleId: null,
    parentId: '1',
    position: 1,
    displayGroup: 1,
    trueCase: false,
    config: {
        entity: 'Customer',
        tagIds: ['123']
    }
};

const sequencesFixture = [
    {
        ...sequenceFixture,
        actionName: ACTION.ADD_TAG
    }
];

const mockBusinessEvents = [
    {
        name: 'checkout.customer.before.login',
        mailAware: true
    },
    {
        name: 'checkout.customer.changed-payment-method',
        mailAware: false
    },
    {
        name: 'checkout.customer.deleted',
        mailAware: true
    }
];

const div = document.createElement('div');
div.id = 'root';
document.body.appendChild(div);

function createWrapper(propsData) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(Shopware.Component.build('sw-flow-trigger'), {
        localVue,
        stubs: {
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-tree': Shopware.Component.build('sw-tree'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item'),
            'sw-loader': true,
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-vnode-renderer': Shopware.Component.build('sw-vnode-renderer'),
            'sw-highlight-text': true,
            'sw-flow-event-change-confirm-modal': {
                template: '<div class="sw-flow-event-change-confirm-modal"></div>'
            }
        },
        provide: {
            businessEventService: {
                getBusinessEvents: jest.fn(() => {
                    return Promise.resolve(mockBusinessEvents);
                })
            },
            repositoryFactory: {}
        },
        propsData: {
            eventName: '',
            ...propsData
        },
        attachTo: document.getElementById('root')
    });
}

enableAutoDestroy(afterEach);

describe('src/module/sw-flow/component/sw-flow-trigger', () => {
    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', flowState);
    });

    it('should display event tree when focus search field', async () => {
        const wrapper = await createWrapper();

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();
    });

    it('should show event name with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'mail.before.send'
        });

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.element.value).toEqual('mail / before / send');
    });

    it('should get event tree data correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.eventTree).toEqual([
            {
                childCount: 1,
                id: 'checkout',
                name: 'checkout',
                parentId: null
            },
            {
                childCount: 3,
                id: 'checkout.customer',
                name: 'customer',
                parentId: 'checkout'
            },
            {
                childCount: 1,
                id: 'checkout.customer.before',
                name: 'before',
                parentId: 'checkout.customer'
            },
            {
                childCount: 0,
                id: 'checkout.customer.before.login',
                name: 'login',
                parentId: 'checkout.customer.before'
            },
            {
                childCount: 0,
                id: 'checkout.customer.changed-payment-method',
                name: 'changed payment method',
                parentId: 'checkout.customer'
            },
            {
                childCount: 0,
                id: 'checkout.customer.deleted',
                name: 'deleted',
                parentId: 'checkout.customer'
            }
        ]);
    });

    it('should emit an event when clicking tree item', async () => {
        const wrapper = await createWrapper();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');

        await wrapper.find('.sw-tree-item__children .sw-tree-item:first-child .sw-tree-item__toggle')
            .trigger('click');

        const transitionStub = wrapper.find('transition-stub')
            .find('.sw-tree-item__children transition-stub .sw-tree-item:last-child');

        await transitionStub.find('.sw-tree-item__content .tree-link').trigger('click');

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.deleted']);
    });

    it('should show search list when user type search term in search field', async () => {
        const wrapper = await createWrapper();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();

        await searchField.setValue('payment');
        await searchField.trigger('input');

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchResults = wrapper.find('.sw-flow-trigger__search-results');
        expect(searchResults.exists()).toBeTruthy();
    });

    it('should show search result correctly', async () => {
        const wrapper = await createWrapper();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        await searchField.setValue('payment');
        await searchField.trigger('input');

        let searchResults = wrapper.findAll('.sw-flow-trigger__search-result');
        expect(searchResults.length).toEqual(1);

        let result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toEqual('Checkout / Customer / Changed payment method');

        await searchField.setValue('deleted');
        await searchField.trigger('input');

        searchResults = wrapper.findAll('.sw-flow-trigger__search-result');
        expect(searchResults.length).toEqual(1);

        result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toEqual('Checkout / Customer / Deleted');
    });

    it('should emit an event when clicking on search item', async () => {
        const wrapper = await createWrapper();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');


        await searchField.setValue('payment');
        await searchField.trigger('input');

        const searchResult = wrapper.find('.sw-flow-trigger__search-result');
        await searchResult.trigger('click');

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.changed-payment-method']);
    });

    it('should able to close the event selection by tab or escape key', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        // Selection is expanded
        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // Press tab button to close the tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Tab'
        }));

        await wrapper.vm.$nextTick();

        // Selection is collapsed
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();

        await searchField.trigger('focus');
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // Press escape button to close the tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Escape'
        }));

        await wrapper.vm.$nextTick();

        // Selection is collapsed
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should able to interact tree by arrow key', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        // Selection is expanded
        let treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(1);
        expect(treeItems.at(0).classes()).toContain('is--focus');
        expect(treeItems.at(0).text()).toEqual('checkout');

        // Press arrow right to open checkout tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright'
        }));

        await wrapper.vm.$nextTick();
        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(2);
        expect(treeItems.at(1).text()).toEqual('customer');

        // Move down to customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown'
        }));

        await wrapper.vm.$nextTick();

        expect(treeItems.at(0).classes()).not.toContain('is--focus');
        expect(treeItems.at(1).classes()).toContain('is--focus');

        // open customer tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright'
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(5);
        expect(treeItems.at(2).text()).toEqual('before');
        expect(treeItems.at(3).text()).toEqual('changed payment method');
        expect(treeItems.at(4).text()).toEqual('deleted');

        // close customer tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowleft'
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(2);

        // Move up to checkout item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowup'
        }));

        await wrapper.vm.$nextTick();

        expect(treeItems.at(1).classes()).not.toContain('is--focus');
        expect(treeItems.at(0).classes()).toContain('is--focus');

        // close checkout tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowleft'
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(1);
    });

    it('should able to emit an event when pressing Enter on the item which has no children', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        // focus trigger input to open event selection
        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        let treeItems = wrapper.findAll('.sw-tree-item');

        // Press arrow right to open checkout tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright'
        }));

        // Move down to customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown'
        }));

        // Press enter to select customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter'
        }));

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        let emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeFalsy();

        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // open customer tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright'
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toEqual(5);
        expect(treeItems.at(2).text()).toEqual('before');
        expect(treeItems.at(3).text()).toEqual('changed payment method');
        expect(treeItems.at(4).text()).toEqual('deleted');

        // move down to changed payment method item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown'
        }));

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown'
        }));

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // changed payment method item is focused
        expect(treeItems.at(3).classes()).toContain('is--focus');

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter'
        }));

        await wrapper.vm.$nextTick();

        emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.changed-payment-method']);

        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should emit an event when pressing Enter on search item', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');


        await searchField.setValue('checkout');
        await searchField.trigger('input');

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter'
        }));

        await wrapper.vm.$nextTick();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.before.login']);
    });

    it('should show confirmation modal when clicking tree item', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection(sequencesFixture));
        const wrapper = await createWrapper();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');

        await wrapper.find('.sw-tree-item__children .sw-tree-item:first-child .sw-tree-item__toggle')
            .trigger('click');

        const transitionStub = wrapper.find('transition-stub')
            .find('.sw-tree-item__children transition-stub .sw-tree-item:last-child');

        await transitionStub.find('.sw-tree-item__content .tree-link').trigger('click');

        const isSequenceEmpty = Shopware.State.getters['swFlowState/isSequenceEmpty'];

        expect(isSequenceEmpty).toEqual(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });

    it('should show confirmation modal when pressing Enter on search item', async () => {
        Shopware.State.commit('swFlowState/setSequences',
            getSequencesCollection(sequencesFixture));

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');


        await searchField.setValue('checkout');
        await searchField.trigger('input');

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter'
        }));

        await wrapper.vm.$nextTick();

        const isSequenceEmpty = Shopware.State.getters['swFlowState/isSequenceEmpty'];

        expect(isSequenceEmpty).toEqual(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });
});
