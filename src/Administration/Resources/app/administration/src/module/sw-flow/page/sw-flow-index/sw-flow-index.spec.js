import { mount } from '@vue/test-utils';
import swFlowIndex from 'src/module/sw-flow/page/sw-flow-index';

Shopware.Component.register('sw-flow-index', swFlowIndex);

async function createWrapper(privileges = []) {
    return mount(await wrapTestComponent('sw-flow-index', {
        sync: true,
    }), {
        global: {
            mocks: {
                $route: {
                    query: {
                        page: 1,
                        limit: 25,
                    },
                },
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
            `,
                },
                'sw-icon': true,
                'sw-button': true,
                'sw-search-bar': true,
                'sw-card-view': true,
                'sw-tabs': true,
                'sw-tabs-item': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => {
                            return Promise.resolve([
                                {
                                    id: '44de136acf314e7184401d36406c1e90',
                                    eventName: 'checkout.order.placed',
                                },
                            ]);
                        },

                        searchIds: () => Promise.resolve({
                            total: 20,
                        }),
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
        },
    });
}

describe('module/sw-flow/page/sw-flow-index', () => {
    it('should be able to create a flow', async () => {
        const wrapper = await createWrapper([
            'flow.creator',
        ]);

        const createButton = wrapper.find('.sw-flow-list__create');

        expect(createButton.attributes().disabled).toBeUndefined();
    });

    it('should be not able to create a flow', async () => {
        const wrapper = await createWrapper();
        const createButton = wrapper.find('.sw-flow-list__create');

        expect(createButton.attributes().disabled).toBe('true');
    });

    it('should be show a number of flows', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(20)');
    });
});
