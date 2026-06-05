import { mount } from '@vue/test-utils';
import swFlowIndex from './index';

/**
 * @sw-package after-sales
 */
function createTabs() {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swFlowIndex.computed.tabs.call({
        $router: {
            push: routerPush,
        },
        $t: (snippet) => snippet,
    });

    return {
        routerPush,
        tabs,
    };
}

async function createWrapper(privileges = []) {
    return mount(
        await wrapTestComponent('sw-flow-index', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        query: {
                            page: 1,
                            limit: 25,
                        },
                        name: 'sw.flow.index.flows',
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
                    'sw-search-bar': true,
                    'sw-card-view': true,
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'mt-tabs': true,
                    'sw-skeleton': true,
                    'router-view': true,
                    'sw-extension-teaser-popover': true,
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

                            searchIds: () =>
                                Promise.resolve({
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

                    searchRankingService: {
                        isValidTerm: (term) => {
                            return term && term.trim().length >= 1;
                        },
                    },

                    feature: {
                        isActive: jest.fn(() => false),
                    },
                },
            },
        },
    );
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

        expect(createButton.attributes('disabled')).toBeDefined();
    });

    it('should be show a number of flows', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-page__smart-bar-amount').text()).toBe('(20)');
    });

    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-flow.general.tabMyFlows',
                name: 'sw.flow.index.flows',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-flow.general.tabFlowTemplates',
                name: 'sw.flow.index.templates',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching flow list route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();
        tabs[1].onClick();

        expect(routerPush).toHaveBeenNthCalledWith(1, { name: 'sw.flow.index.flows' });
        expect(routerPush).toHaveBeenNthCalledWith(2, { name: 'sw.flow.index.templates' });
    });
});
