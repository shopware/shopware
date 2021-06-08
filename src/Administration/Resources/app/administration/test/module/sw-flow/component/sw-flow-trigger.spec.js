import { createLocalVue, shallowMount, enableAutoDestroy } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-trigger';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/utils/sw-vnode-renderer';

import Vuex from 'vuex';
import flowState from 'src/module/sw-flow/state/flow.state';

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
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': true,
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-tree': Shopware.Component.build('sw-tree'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item'),
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-vnode-renderer': Shopware.Component.build('sw-vnode-renderer'),
            'sw-highlight-text': true
        },
        provide: {
            businessEventService: {
                getBusinessEvents: jest.fn(() => {
                    return Promise.resolve(mockBusinessEvents);
                })
            },
            repositoryFactory: {},
            validationService: {}
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

        const searchField = wrapper.find('#sw-field--searchTerm');
        await searchField.trigger('focus');

        eventTree = wrapper.find('.sw-tree');
        expect(eventTree.exists()).toBeTruthy();
    });

    it('should show event name with correct format', async () => {
        const wrapper = await createWrapper({
            eventName: 'mail.before.send'
        });

        const searchField = wrapper.find('#sw-field--searchTerm');
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

        const searchField = wrapper.find('#sw-field--searchTerm');
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

        const searchField = wrapper.find('#sw-field--searchTerm');
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

        const searchField = wrapper.find('#sw-field--searchTerm');
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

        const searchField = wrapper.find('#sw-field--searchTerm');
        await searchField.trigger('focus');


        await searchField.setValue('payment');
        await searchField.trigger('input');

        const searchResult = wrapper.find('.sw-flow-trigger__search-result');
        await searchResult.trigger('click');

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
        expect(emittedEvent[0]).toEqual(['checkout.customer.changed-payment-method']);
    });
});
