import { shallowMount } from '@vue/test-utils';
import swFlowListFlowTemplates from 'src/module/sw-flow/view/listing/sw-flow-list-flow-templates';

const { Context } = Shopware;
const { EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-flow-list-flow-templates', swFlowListFlowTemplates);

const mockData = [
    {
        id: '44de136acf314e7184401d36406c1e90',
        name: 'test flow template',
        config: {
            eventName: 'checkout.order.placed'
        }
    }
];

async function createWrapper(privileges = []) {
    return shallowMount(await Shopware.Component.build('sw-flow-list-flow-templates'), {
        mocks: {
            $route: {
                query: {
                    page: 1,
                    limit: 25
                }
            }
        },

        provide: {
            repositoryFactory: {
                create: () => ({
                    search: () => {
                        return Promise.resolve(new EntityCollection('', '', Context.api, null, mockData, 1));
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

            searchRankingService: {}
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
                `
            },
            'sw-icon': true,
            'sw-button': true,
            'sw-entity-listing': {
                props: ['items'],
                template: `
                    <div class="sw-data-grid">
                    <div class="sw-data-grid__row" v-for="item in items">
                        <slot name="column-name" v-bind="{ item }"></slot>
                        <slot name="column-createFlow" v-bind="{ item }"></slot>
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

describe('module/sw-flow/view/listing/sw-flow-list-flow-templates', () => {
    it('should be able to create a flow from template', async () => {
        const wrapper = await createWrapper([
            'flow.creator'
        ]);
        await flushPromises();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBe(true);

        expect(createFlowLink.attributes().disabled).toBe(undefined);
    });

    it('should not be able to create a flow from template', async () => {
        const wrapper = await createWrapper([
            'flow.viewer'
        ]);
        await flushPromises();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBe(true);

        expect(createFlowLink.attributes().disabled).toBe('disabled');
    });

    it('should be able to redirect to create flow page from flow template', async () => {
        const wrapper = await createWrapper([
            'flow.creator'
        ]);
        await flushPromises();
        await wrapper.find('.sw-flow-list-my-flows__content__create-flow-link').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.flow.create',
            params: { flowTemplateId: '44de136acf314e7184401d36406c1e90' }
        });
    });

    it('should be able to view detail flow template', async () => {
        const wrapper = await createWrapper([
            'flow.creator'
        ]);

        await flushPromises();
        await wrapper.find('.sw-flow-list-my-flows__content__update-flow-template-link').trigger('click');

        const routerPush = wrapper.vm.$router.push;

        expect(routerPush).toHaveBeenLastCalledWith({
            name: 'sw.flow.detail',
            params: { id: '44de136acf314e7184401d36406c1e90' },
            query: {
                type: 'template'
            }
        });

        wrapper.vm.$router.push = jest.fn();
        wrapper.vm.onEditFlow({});
        await flushPromises();

        expect(wrapper.vm.$router.push).toBeCalledTimes(0);
    });
});
