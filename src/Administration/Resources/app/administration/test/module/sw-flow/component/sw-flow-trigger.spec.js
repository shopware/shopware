import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/component/sw-flow-trigger';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/utils/sw-vnode-renderer';

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

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-flow-trigger'), {
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-field-error': {
                template: '<div></div>'
            },
            'sw-container': {
                template: '<div><slot></slot></div>'
            },
            'sw-tree': Shopware.Component.build('sw-tree'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item'),
            'sw-icon': {
                template: '<div></div>'
            },
            'sw-vnode-renderer': Shopware.Component.build('sw-vnode-renderer')
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
            eventName: ''
        }
    });
}

describe('src/module/sw-flow/component/sw-flow-trigger', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be focus display tree', async () => {
        expect(wrapper.vm.displayTree).toBe(false);

        await wrapper.vm.onFocusTrigger();
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.displayTree).toBe(true);
    });

    it('should be get event tree', async () => {
        await wrapper.vm.$nextTick();

        expect(wrapper.vm.events).toEqual([
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
        await wrapper.vm.$nextTick();
        await wrapper.vm.onFocusTrigger();

        const treeItem = wrapper.find('.tree-items .sw-tree-item:first-child .sw-tree-item__toggle');
        await treeItem.trigger('click');

        await wrapper.find('.sw-tree-item__children .sw-tree-item:first-child .sw-tree-item__toggle')
            .trigger('click');

        const transitionStub = wrapper.find('transition-stub')
            .find('.sw-tree-item__children transition-stub .sw-tree-item:last-child');

        await transitionStub.find('.sw-tree-item__content .tree-link').trigger('click');

        const emittedEvent = wrapper.emitted()['option-select'];
        expect(emittedEvent).toBeTruthy();
    });
});
