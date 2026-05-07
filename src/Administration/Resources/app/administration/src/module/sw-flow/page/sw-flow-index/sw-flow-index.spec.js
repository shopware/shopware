import { mount } from '@vue/test-utils';

/**
 * @sw-package after-sales
 */
async function createWrapper(privileges = [], { routeName = 'sw.flow.index.flows', routerPush = jest.fn() } = {}) {
    return mount(
        await wrapTestComponent('sw-flow-index', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    $route: {
                        name: routeName,
                        query: {
                            page: 1,
                            limit: 25,
                        },
                    },
                    $router: {
                        push: routerPush,
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
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-tabs': true,
                    'sw-tabs-item': true,
                    'mt-tabs': {
                        name: 'mt-tabs',
                        template: '<div class="mt-tabs-stub"></div>',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                required: false,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                required: false,
                                default: null,
                            },
                            small: {
                                type: Boolean,
                                required: false,
                                default: true,
                            },
                            routeTabs: {
                                type: Boolean,
                                required: false,
                                default: false,
                            },
                        },
                    },
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
                },
            },
        },
    );
}

describe('module/sw-flow/page/sw-flow-index', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

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

    it('should render flow listing route tabs with mt-tabs', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const routerPush = jest.fn();
        const wrapper = await createWrapper([], {
            routeName: 'sw.flow.index.templates',
            routerPush,
        });

        const tabs = wrapper.findComponent({ name: 'mt-tabs' });
        const items = tabs.props('items');

        expect(tabs.props('positionIdentifier')).toBe('sw-flow-listing');
        expect(tabs.props('defaultItem')).toBe('sw.flow.index.templates');
        expect(tabs.props('small')).toBe(false);
        expect(tabs.props('routeTabs')).toBe(true);
        expect(items.map((item) => item.name)).toEqual([
            'sw.flow.index.flows',
            'sw.flow.index.templates',
        ]);

        items[0].onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.flow.index.flows' });
    });
});
