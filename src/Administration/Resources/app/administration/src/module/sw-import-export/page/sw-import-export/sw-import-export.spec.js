/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper({
    featureActive = false,
    routeName = 'sw.import.export.index.import',
    routerPush = jest.fn(),
} = {}) {
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
                'sw-tabs': {
                    name: 'sw-tabs',
                    template: '<div class="sw-tabs"><slot></slot></div>',
                    props: ['positionIdentifier'],
                },
                'sw-tabs-item': {
                    name: 'sw-tabs-item',
                    template: '<div class="sw-tabs-item"><slot></slot></div>',
                    props: ['route'],
                },
                'mt-tabs': {
                    name: 'mt-tabs',
                    template: '<div class="mt-tabs"></div>',
                    props: {
                        defaultItem: {
                            type: String,
                            required: false,
                            default: undefined,
                        },
                        items: {
                            type: Array,
                            required: true,
                        },
                        positionIdentifier: {
                            type: String,
                            required: true,
                        },
                    },
                },
                'router-view': true,
            },
            mocks: {
                $route: {
                    name: routeName,
                },
                $router: {
                    push: routerPush,
                },
            },
            provide: {
                feature: {
                    isActive: (feature) => feature === 'v6.8.0.0' && featureActive,
                },
            },
        },
    });
}

describe('src/module/sw-import-export/page/sw-import-export', () => {
    it('should render deprecated tabs when the major feature flag is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);

        const tabs = wrapper.findAllComponents({ name: 'sw-tabs-item' });
        expect(tabs).toHaveLength(3);
        expect(tabs.map((tab) => tab.props('route'))).toEqual([
            { name: 'sw.import.export.index.import' },
            { name: 'sw.import.export.index.export' },
            { name: 'sw.import.export.index.profiles' },
        ]);
    });

    it('should render meteor tabs when the major feature flag is active', async () => {
        const wrapper = await createWrapper({
            featureActive: true,
            routeName: 'sw.import.export.index.export',
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-import-export');
        expect(tabs.props('defaultItem')).toBe('sw.import.export.index.export');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-import-export.page.importTab',
                name: 'sw.import.export.index.import',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.exportTab',
                name: 'sw.import.export.index.export',
                onClick: expect.any(Function),
            }),
            expect.objectContaining({
                label: 'sw-import-export.page.profileTab',
                name: 'sw.import.export.index.profiles',
                onClick: expect.any(Function),
            }),
        ]);
        expect(wrapper.findComponent({ name: 'sw-tabs' }).exists()).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        const wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });
        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const profilesTab = tabs.props('items').find((item) => item.name === 'sw.import.export.index.profiles');

        profilesTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.import.export.index.profiles' });
    });
});
