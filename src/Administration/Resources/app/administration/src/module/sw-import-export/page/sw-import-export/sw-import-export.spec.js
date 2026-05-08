/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';

async function createWrapper(routeName = 'sw.import.export.index.import') {
    return mount(
        await wrapTestComponent('sw-import-export', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-page': {
                        template: '<div><slot name="content"></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div><slot></slot></div>',
                    },
                    'sw-tabs': await wrapTestComponent('sw-tabs'),
                    'sw-tabs-deprecated': await wrapTestComponent('sw-tabs-deprecated', { sync: true }),
                    'sw-tabs-item': await wrapTestComponent('sw-tabs-item'),
                    'mt-tabs': {
                        props: [
                            'items',
                            'defaultItem',
                            'positionIdentifier',
                        ],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" :position-identifier="positionIdentifier" />',
                    },
                },
                mocks: {
                    $route: {
                        name: routeName,
                    },
                    $router: {
                        push: jest.fn(),
                    },
                },
            },
        },
    );
}

describe('module/sw-import-export/page/sw-import-export', () => {
    beforeEach(() => {
        global.activeFeatureFlags = [''];
    });

    it('should render legacy tabs when V6_8_0_0 is inactive', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(true);
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should render mt-tabs when V6_8_0_0 is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper();
        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-import-export.page.importTab',
                name: 'import',
                route: { name: 'sw.import.export.index.import' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-import-export.page.exportTab',
                name: 'export',
                route: { name: 'sw.import.export.index.export' },
                onClick: expect.any(Function),
            },
            {
                label: 'sw-import-export.page.profileTab',
                name: 'profiles',
                route: { name: 'sw.import.export.index.profiles' },
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('import');
        expect(mtTabs.props('positionIdentifier')).toBe('sw-import-export');
        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(false);
    });

    it('should use the current route as default mt-tabs item', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        const wrapper = await createWrapper('sw.import.export.index.profiles');

        expect(wrapper.getComponent('mt-tabs-stub').props('defaultItem')).toBe('profiles');
    });
});
