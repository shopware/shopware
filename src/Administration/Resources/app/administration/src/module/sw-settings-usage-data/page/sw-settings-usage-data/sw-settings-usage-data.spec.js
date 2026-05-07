import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */
async function createWrapper({ route, routerPush } = {}) {
    return mount(
        await wrapTestComponent('sw-settings-usage-data', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                    },
                    'sw-card-view': await wrapTestComponent('sw-card-view'),
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'router-view': true,
                    'sw-search-bar': true,
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'mt-tabs': {
                        name: 'mt-tabs',
                        props: {
                            items: {
                                type: Array,
                                required: true,
                            },
                            positionIdentifier: {
                                type: String,
                                default: null,
                            },
                            defaultItem: {
                                type: String,
                                default: '',
                            },
                            routeTabs: {
                                type: Boolean,
                                default: false,
                            },
                        },
                        template: '<div class="mt-tabs-stub"></div>',
                    },
                    'sw-error-summary': true,
                    'sw-extension-component-section': true,
                },
                mocks: {
                    $route: route ?? {
                        name: 'sw.settings.usage.data.index.general',
                        params: {},
                    },
                    $router: {
                        push: routerPush ?? jest.fn(),
                    },
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should show tabs', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({
            name: 'sw-tabs-deprecated__wrapped',
        });
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
    });

    it('should render route-backed mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const routerPush = jest.fn();

        wrapper = await createWrapper({ routerPush });

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-settings-usage-data');
        expect(tabs.props('defaultItem')).toBe('sw.settings.usage.data.index.general');
        expect(tabs.props('routeTabs')).toBe(true);

        const items = tabs.props('items');
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-settings-usage-data-general.tabHeadline',
                name: 'sw.settings.usage.data.index.general',
            }),
        ]);

        items[0].onClick();
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.usage.data.index.general' });
    });
});
