import { createLocalVue, shallowMount } from '@vue/test-utils';
import swFlowTrigger from 'src/module/sw-flow/component/sw-flow-trigger';
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

Shopware.Component.register('sw-flow-trigger', swFlowTrigger);

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
    id: '2',
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

const sequencesFixture = [
    {
        ...sequenceFixture,
        actionName: ACTION.ADD_TAG,
    },
];

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

const mockTranslations = {
    'sw-flow.triggers.before': 'Before',
    'sw-flow.triggers.mail': 'Mail',
    'sw-flow.triggers.send': 'Send',
    'sw-flow.triggers.checkout': 'Checkout',
    'sw-flow.triggers.customer': 'Customer',
    'sw-flow.triggers.login': 'Login',
    'sw-flow.triggers.changedPaymentMethod': 'Changed payment method',
    'sw-flow.triggers.deleted': 'Deleted',
};

const div = document.createElement('div');
div.id = 'root';
document.body.appendChild(div);

async function createWrapper(propsData) {
    const localVue = createLocalVue();
    localVue.use(Vuex);

    return shallowMount(await Shopware.Component.build('sw-flow-trigger'), {
        localVue,
        mocks: {
            $tc(translationKey) {
                return mockTranslations[translationKey] ? mockTranslations[translationKey] : translationKey;
            },

            $te(translationKey) {
                return !!mockTranslations[translationKey];
            },
        },
        stubs: {
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-container': {
                template: '<div><slot></slot></div>',
            },
            'sw-tree': await Shopware.Component.build('sw-tree'),
            'sw-tree-item': await Shopware.Component.build('sw-tree-item'),
            'sw-loader': true,
            'sw-icon': {
                template: '<div></div>',
            },
            'sw-vnode-renderer': await Shopware.Component.build('sw-vnode-renderer'),
            'sw-highlight-text': true,
            'sw-flow-event-change-confirm-modal': {
                template: '<div class="sw-flow-event-change-confirm-modal"></div>',
            },
            'sw-tree-input-field': true,
        },
        provide: {
            businessEventService: {
                getBusinessEvents: jest.fn(() => {
                    return Promise.resolve(mockBusinessEvents);
                }),
            },
            repositoryFactory: {},
        },
        propsData: {
            eventName: '',
            ...propsData,
        },
        attachTo: document.body,
    });
}

describe('src/module/sw-flow/component/sw-flow-trigger', () => {
    beforeAll(() => {
        Shopware.Service().register('ruleConditionDataProviderService', () => {
            return {
                getRestrictedRules: () => Promise.resolve([]),
            };
        });

        Shopware.Service().register('businessEventService', () => {
            return {
                getBusinessEvents: () => Promise.resolve(mockBusinessEvents),
            };
        });

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

    it('should display event tree with event get from flow state', async () => {
        Shopware.State.commit('swFlowState/setTriggerEvents', mockBusinessEvents);

        const wrapper = await createWrapper();
        await wrapper.setProps({
            isUnknownTrigger: true,
        });

        let eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeFalsy();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.attributes().placeholder).toBe('sw-flow.detail.trigger.unknownTriggerPlaceholder');
        await searchField.trigger('focus');

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();
        Shopware.State.commit('swFlowState/setTriggerEvents', []);
    });

    it('should show event name with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'mail.before.send',
        });

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.element.value).toBe('Mail / Before / Send');
    });

    it('should show event name from custom event snippet with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'swag.open.the_doors',
        });

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        expect(searchField.element.value).toBe('swag / open / the doors');
    });

    it('should get event tree data correctly', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.eventTree).toEqual([
            {
                childCount: 1,
                id: 'checkout',
                name: 'Checkout',
                parentId: null,
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 3,
                id: 'checkout.customer',
                name: 'Customer',
                parentId: 'checkout',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 1,
                id: 'checkout.customer.before',
                name: 'Before',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.before.login',
                name: 'Login',
                parentId: 'checkout.customer.before',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.changed-payment-method',
                name: 'Changed payment method',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.deleted',
                name: 'Deleted',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
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
        expect(searchResults).toHaveLength(1);

        let result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toBe('Checkout / Customer / Changed payment method');

        await searchField.setValue('deleted');
        await searchField.trigger('input');

        searchResults = wrapper.findAll('.sw-flow-trigger__search-result');
        expect(searchResults).toHaveLength(1);

        result = wrapper.find('sw-highlight-text-stub');
        expect(result.attributes().text).toBe('Checkout / Customer / Deleted');
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

    it('should be able to close the event selection by tab key', async () => {
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
            key: 'Tab',
        }));

        await wrapper.vm.$nextTick();

        // Selection is collapsed
        eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeFalsy();
    });

    it('should be able to close the event selection by escape key', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');
        let eventSelection = wrapper.find('.sw-flow-trigger__event-selection');
        expect(eventSelection.exists()).toBeTruthy();

        // Press escape button to close the tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Escape',
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
        expect(treeItems).toHaveLength(1);
        expect(treeItems.at(0).classes()).toContain('is--focus');
        expect(treeItems.at(0).text()).toBe('Checkout');

        // Press arrow right to open checkout tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright',
        }));

        await wrapper.vm.$nextTick();
        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(2);
        expect(treeItems.at(1).text()).toBe('Customer');

        // Move down to customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown',
        }));

        await wrapper.vm.$nextTick();

        expect(treeItems.at(0).classes()).not.toContain('is--focus');
        expect(treeItems.at(1).classes()).toContain('is--focus');

        // open customer tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowright',
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(5);
        expect(treeItems.at(2).text()).toBe('Before');
        expect(treeItems.at(3).text()).toBe('Changed payment method');
        expect(treeItems.at(4).text()).toBe('Deleted');

        // close customer tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowleft',
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(2);

        // Move up to checkout item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowup',
        }));

        await wrapper.vm.$nextTick();

        expect(treeItems.at(1).classes()).not.toContain('is--focus');
        expect(treeItems.at(0).classes()).toContain('is--focus');

        // close checkout tree
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowleft',
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(1);
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
            key: 'Arrowright',
        }));

        // Move down to customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown',
        }));

        // Press enter to select customer item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter',
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
            key: 'Arrowright',
        }));

        await wrapper.vm.$nextTick();

        treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems).toHaveLength(5);
        expect(treeItems.at(2).text()).toBe('Before');
        expect(treeItems.at(3).text()).toBe('Changed payment method');
        expect(treeItems.at(4).text()).toBe('Deleted');

        // move down to changed payment method item
        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown',
        }));

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Arrowdown',
        }));

        await wrapper.vm.$nextTick();
        await wrapper.vm.$nextTick();

        // changed payment method item is focused
        expect(treeItems.at(3).classes()).toContain('is--focus');

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter',
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
            key: 'Enter',
        }));

        await wrapper.vm.$nextTick();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.before.login']);
    });

    it('should show confirmation modal when clicking tree item', async () => {
        Shopware.State.commit(
            'swFlowState/setSequences',
            getSequencesCollection(sequencesFixture),
        );
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

        expect(isSequenceEmpty).toBe(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });

    it('should show confirmation modal when pressing Enter on search item', async () => {
        Shopware.State.commit(
            'swFlowState/setSequences',
            getSequencesCollection(sequencesFixture),
        );

        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');


        await searchField.setValue('checkout');
        await searchField.trigger('input');

        window.document.dispatchEvent(new KeyboardEvent('keydown', {
            key: 'Enter',
        }));

        await wrapper.vm.$nextTick();

        const isSequenceEmpty = Shopware.State.getters['swFlowState/isSequenceEmpty'];

        expect(isSequenceEmpty).toBe(false);
        expect(wrapper.find('.sw-flow-event-change-confirm-modal').exists()).toBeTruthy();
    });

    it('should show tool tip when trigger has only stop flow action', async () => {
        const wrapper = await createWrapper();
        await wrapper.vm.$nextTick();
        await wrapper.setData({
            events: [
                {
                    name: 'mail.sent',
                    mailAware: true,
                    aware: [],
                },
                ...wrapper.vm._data.events,
            ],
        });

        const searchField = wrapper.find('.sw-flow-trigger__input-field');
        await searchField.trigger('focus');

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');

        const treeItemLink = await wrapper.find('.sw-tree-item__content .tree-link');
        await treeItemLink.trigger('click');

        const treeItemContent = await wrapper.find('.sw-tree-item__content');
        expect(treeItemContent.attributes()['tooltip-id']).toBeTruthy();

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeFalsy();
    });

    it('should not translate if the snippet is not exists', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm.getEventNameTranslated('send')).toBe('Send');
        expect(wrapper.vm.getEventNameTranslated('test_event_name')).toBe('test event name');
    });

    it('should get event tree data correctly with custom event', async () => {
        const wrapper = await createWrapper(null);

        mockBusinessEvents.push({
            name: 'swag.before.open.the_doors',
            mailAware: true,
            aware: ['Shopware\\Core\\Framework\\Event\\CustomEventAware'],
        });

        expect(wrapper.vm.eventTree).toEqual([
            {
                childCount: 1,
                id: 'checkout',
                name: 'Checkout',
                parentId: null,
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 3,
                id: 'checkout.customer',
                name: 'Customer',
                parentId: 'checkout',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 1,
                id: 'checkout.customer.before',
                name: 'Before',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.before.login',
                name: 'Login',
                parentId: 'checkout.customer.before',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.changed-payment-method',
                name: 'Changed payment method',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 0,
                id: 'checkout.customer.deleted',
                name: 'Deleted',
                parentId: 'checkout.customer',
                disabled: false,
                disabledToolTipText: null,
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag',
                name: 'swag',
                parentId: null,
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before',
                name: 'Before',
                parentId: 'swag',
            },
            {
                childCount: 1,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before.open',
                name: 'open',
                parentId: 'swag.before',
            },
            {
                childCount: 0,
                disabled: false,
                disabledToolTipText: null,
                id: 'swag.before.open.the_doors',
                name: 'the doors',
                parentId: 'swag.before.open',
            },
        ]);
    });
});
