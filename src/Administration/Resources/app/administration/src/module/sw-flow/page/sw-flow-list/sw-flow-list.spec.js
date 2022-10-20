import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/page/sw-flow-list';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-list'), {
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },

        provide: { repositoryFactory: {
            create: () => ({
                search: () => {
                    return Promise.resolve([
                        {
                            id: '44de136acf314e7184401d36406c1e90',
                            eventName: 'checkout.order.placed'
                        }
                    ]);
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
        },
        searchRankingService: {} },

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
                `
            },
            'sw-context-menu-item': true,
            'sw-empty-state': true,
            'sw-search-bar': true
        }
    });
}

describe('module/sw-flow/page/sw-flow-list', () => {
    it('should be able to create a flow ', async () => {
        const wrapper = createWrapper([
            'flow.creator'
        ]);

        const createButton = wrapper.find('.sw-flow-list__create');

        expect(createButton.attributes().disabled).toBeFalsy();
    });

    it('should be not able to create a flow ', async () => {
        const wrapper = createWrapper();
        const createButton = wrapper.find('.sw-flow-list__create');

        expect(createButton.attributes().disabled).toBeTruthy();
    });

    it('should be able to edit a flow ', async () => {
        const wrapper = createWrapper([
            'flow.editor'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-flow-list__item-edit');
        expect(editMenuItem.exists()).toBeTruthy();
        expect(editMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should be not able to edit a flow ', async () => {
        const wrapper = createWrapper([
            'flow.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const editMenuItem = wrapper.find('.sw-flow-list__item-edit');

        expect(editMenuItem.exists()).toBeTruthy();
        expect(editMenuItem.text()).toContain('global.default.view');
    });

    it('should be able to delete a flow ', async () => {
        const wrapper = createWrapper([
            'flow.deleter'
        ]);
        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-flow-list__item-delete');
        expect(deleteMenuItem.exists()).toBeTruthy();
        expect(deleteMenuItem.attributes().disabled).toBeFalsy();
    });

    it('should be not able to delete a flow ', async () => {
        const wrapper = createWrapper([
            'flow.viewer'
        ]);

        await wrapper.vm.$nextTick();

        const deleteMenuItem = wrapper.find('.sw-flow-list__item-delete');
        expect(deleteMenuItem.exists()).toBeTruthy();
        expect(deleteMenuItem.attributes().disabled).toBeTruthy();
    });

    it('should show trigger column correctly', async () => {
        const wrapper = createWrapper([
            'flow.viewer'
        ]);

        await wrapper.vm.$nextTick();

        const item = wrapper.find('.sw-data-grid__row');
        expect(item.text()).toContain('global.businessEvents.checkout_order_placed');
        expect(item.text()).toContain('checkout.order.placed');
    });
});
