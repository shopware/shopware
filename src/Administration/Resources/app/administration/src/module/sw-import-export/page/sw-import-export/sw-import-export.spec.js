/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ route, routerPush } = {}) {
    return mount(await wrapTestComponent('sw-import-export', { sync: true }), {
        global: {
            stubs: {
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="content"></slot>
                        </div>
                    `,
                },
                'sw-card-view': {
                    template: '<div class="sw-card-view"><slot></slot></div>',
                },
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
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
                'router-link': true,
                'router-view': true,
                'sw-extension-component-section': true,
            },
            mocks: {
                $route: route ?? {
                    name: 'sw.import.export.index.import',
                    params: {},
                },
                $router: {
                    push: routerPush ?? jest.fn(),
                },
            },
        },
    });
}

describe('src/module/sw-import-export/page/sw-import-export', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [];
    });

    it('should keep rendering legacy sw-tabs while the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(true);
        expect(wrapper.find('.mt-tabs-stub').exists()).toBe(false);
    });

    it('should render route-backed mt-tabs when the major feature flag is enabled', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];
        const routerPush = jest.fn();

        const wrapper = await createWrapper({ routerPush });

        const tabs = wrapper.getComponent('.mt-tabs-stub');
        expect(tabs.props('positionIdentifier')).toBe('sw-import-export');
        expect(tabs.props('defaultItem')).toBe('sw.import.export.index.import');
        expect(tabs.props('routeTabs')).toBe(true);

        const items = tabs.props('items');
        expect(items).toEqual([
            expect.objectContaining({
                label: 'sw-import-export.page.importTab',
                name: 'sw.import.export.index.import',
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.exportTab',
                name: 'sw.import.export.index.export',
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.profileTab',
                name: 'sw.import.export.index.profiles',
            }),
        ]);

        items[1].onClick();
        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.import.export.index.export' });
    });
});
