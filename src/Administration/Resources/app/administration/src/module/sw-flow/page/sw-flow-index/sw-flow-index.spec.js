import { mount } from '@vue/test-utils';

/**
 * @sw-package after-sales
 */
async function createWrapper(privileges = [], routeName = 'sw.flow.index.flows') {
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
                        push: jest.fn(),
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
                        props: [
                            'items',
                            'defaultItem',
                            'positionIdentifier',
                        ],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
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
        global.activeFeatureFlags = [''];
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

    it('should render legacy tabs when major flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('sw-tabs-stub').exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render mt-tabs when major flag is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-flow.general.tabMyFlows',
                name: 'flows',
                route: { name: 'sw.flow.index.flows' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-flow.general.tabFlowTemplates',
                name: 'templates',
                route: { name: 'sw.flow.index.templates' },
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('flows');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-flow-listing');
        expect(wrapper.find('sw-tabs-stub').exists()).toBe(false);
    });

    it('should use the templates tab as mt-tabs default on template route', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper([], 'sw.flow.index.templates');
        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('defaultItem')).toBe('templates');
    });
});
