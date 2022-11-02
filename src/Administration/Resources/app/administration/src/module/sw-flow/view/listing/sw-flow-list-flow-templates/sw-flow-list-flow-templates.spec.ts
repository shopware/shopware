import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/view/listing/sw-flow-list-flow-templates';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-list-flow-templates'), {
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
                        return Promise.resolve([
                            {
                                id: '44de136acf314e7184401d36406c1e90',
                                name: 'test flow template',
                                config: {
                                    eventName: 'checkout.order.placed'
                                }
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
                            <slot name="column-eventName" v-bind="{ item }"></slot>
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
    it('should be able to create a flow from template ', async () => {
        const wrapper = createWrapper([
            'flow.creator'
        ]);
        await wrapper.vm.$nextTick();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBeTruthy();

        expect(createFlowLink.attributes().disabled).toBeFalsy();
    });

    it('should not be able to create a flow from template ', async () => {
        const wrapper = createWrapper([
            'flow.viewer'
        ]);
        await wrapper.vm.$nextTick();

        const createFlowLink = wrapper.find('.sw-flow-list-my-flows__content__create-flow-link');
        expect(createFlowLink.exists()).toBeTruthy();

        expect(createFlowLink.attributes().disabled).toBeTruthy();
    });
});
