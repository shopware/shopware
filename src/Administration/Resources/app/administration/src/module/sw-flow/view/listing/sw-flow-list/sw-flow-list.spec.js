import { mount } from '@vue/test-utils';
import flowState from 'src/module/sw-flow/state/flow.state';

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
        name: 'checkout.order.placed',
        mailAware: true,
        aware: ['Shopware\\Core\\Framework\\Event\\OrderAware'],
    },
];

const flowData = [
    {
        id: '44de136acf314e7184401d36406c1e90',
        eventName: 'checkout.order.placed',
    },
];

async function createWrapper(privileges = [], hasSnippetFromApp = false, customFlowData = flowData) {
    return mount(await wrapTestComponent('sw-flow-list', { sync: true }), {
        global: {
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
                'sw-icon': true,
                'sw-button': true,
                'sw-entity-listing': {
                    props: ['items'],
                    template: `
                    <div class="sw-data-grid">
                        <div class="sw-data-grid__row" v-for="item in items">
                            <slot name="column-eventName" v-bind="{ item }"></slot>
                            <slot name="actions" v-bind="{ item }"></slot>
                        </div>
                    </div>
                `,
                },
                'sw-card': await wrapTestComponent('sw-card'),
                'sw-card-deprecated': await wrapTestComponent('sw-card-deprecated', { sync: true }),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item'),
                'sw-empty-state': true,
                'sw-search-bar': true,
                'sw-alert': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve(customFlowData);
                        },
                        clone: jest.fn(() => Promise.resolve({
                            id: '0e6b005ca7a1440b8e87ac3d45ed5c9f',
                        })),
                    }),
                },

                acl: {
                    can: (identifier) => {
                        if (!identifier) {
                            return true;
                        }

                        return privileges.includes(identifier);
                    },
                },

                searchRankingService: {},
            },
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
                $tc: (key) => {
                    if (key === 'global.businessEvents.checkout_order_placed' && !hasSnippetFromApp) {
                        return 'Check order place';
                    }

                    return key;
                },

                $te(key) {
                    return !(key === 'global.businessEvents.checkout_order_placed' && hasSnippetFromApp);
                },
            },
        },
    });
}

describe('module/sw-flow/view/listing/sw-flow-list-my-flows', () => {
    Shopware.Service().register('businessEventService', () => {
        return {
            getBusinessEvents: () => Promise.resolve(mockBusinessEvents),
        };
    });

    beforeAll(() => {
        Shopware.State.registerModule('swFlowState', {
            ...flowState,
            state: {
                triggerEvents: [],
            },
        });
    });

    it('should be able to duplicate a flow', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);
        await flushPromises();

        const duplicateMenuItem = wrapper.find('.sw-flow-list__item-duplicate');

        expect(duplicateMenuItem.exists()).toBe(true);
        expect(duplicateMenuItem.attributes().disabled).toBeUndefined();
    });

    it('should be not able to duplicate a flow', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-flow-list__item-duplicate');

        expect(editMenuItem.exists()).toBe(true);
        expect(editMenuItem.text()).toContain('global.default.duplicate');
    });

    it('should be able to edit a flow', async () => {
        const wrapper = await createWrapper([
            'flow.editor',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-flow-list__item-edit');
        expect(editMenuItem.exists()).toBe(true);
        expect(editMenuItem.attributes().disabled).toBeUndefined();
    });

    it('should be not able to edit a flow', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);
        await flushPromises();

        const editMenuItem = wrapper.find('.sw-flow-list__item-edit');

        expect(editMenuItem.exists()).toBe(true);
        expect(editMenuItem.text()).toContain('global.default.view');
    });

    it('should be able to delete a flow', async () => {
        const wrapper = await createWrapper([
            'flow.deleter',
        ]);
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-flow-list__item-delete');
        expect(deleteMenuItem.exists()).toBe(true);
        expect(deleteMenuItem.classes()).not.toContain('is--disabled');
    });

    it('should be not able to delete a flow', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);

        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-flow-list__item-delete');

        expect(deleteMenuItem.exists()).toBe(true);
        expect(deleteMenuItem.classes()).toContain('is--disabled');
    });

    it('should show trigger column correctly', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ]);

        await flushPromises();

        const item = wrapper.find('.sw-data-grid__row');
        expect(item.text()).toContain('Check order place');
        expect(item.text()).toContain('checkout.order.placed');
    });

    it('should show trigger column correctly with unknown trigger', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ], false, [
            {
                id: '44de136acf314e7184401d36406c1e90',
                eventName: 'checkout.order.custom',
            },
        ]);

        await flushPromises();

        const item = wrapper.find('.sw-data-grid__row');
        expect(item.text()).toContain('sw-flow.list.unknownTrigger');
    });

    it('should show custom trigger column correctly', async () => {
        const wrapper = await createWrapper([
            'flow.viewer',
        ], true);

        await wrapper.vm.$nextTick();

        const item = wrapper.find('.sw-data-grid__row');
        expect(item.text()).toContain('sw-flow-custom-event.flow-list.checkout_order_placed');
        expect(item.text()).toContain('checkout.order.placed');
    });

    it('should be show the success message after duplicate flow', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);
        await flushPromises();
        wrapper.vm.createNotificationSuccess = jest.fn();
        const routerPush = wrapper.vm.$router.push;

        await wrapper.vm.onDuplicateFlow({
            id: '44de136acf314e7184401d36406c1e90',
            name: 'test flow',
        });
        await flushPromises();

        expect(wrapper.vm.createNotificationSuccess).toHaveBeenCalled();
        wrapper.vm.createNotificationSuccess.mockRestore();

        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.flow.detail',
            params: { id: '0e6b005ca7a1440b8e87ac3d45ed5c9f' },
        });
    });
});
