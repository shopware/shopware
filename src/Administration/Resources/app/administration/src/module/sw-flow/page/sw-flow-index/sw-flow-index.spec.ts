import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-flow/page/sw-flow-index';

function createWrapper(privileges = []) {
    return shallowMount(Shopware.Component.build('sw-flow-index'), {
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
                                eventName: 'checkout.order.placed'
                            }
                        ]);
                    },

                    searchIds: () => Promise.resolve({
                        total: 20
                    })
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
            'sw-search-bar': true,
            'sw-card-view': true,
            'sw-tabs': true,
            'sw-tabs-item': true,
        }
    });
}

describe('module/sw-flow/page/sw-flow-index', () => {
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

    it('should be show a number of flows ', async () => {
        const wrapper = createWrapper();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(20)');
    });
});
