import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */
async function createWrapper({
    featureActive = false,
    routeName = 'sw.settings.usage.data.index.general',
    routerPush = jest.fn(),
} = {}) {
    return mount(
        await wrapTestComponent('sw-settings-usage-data', {
            sync: true,
        }),
        {
            global: {
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
                    'sw-tabs-item': true,
                    'sw-error-summary': true,
                    'sw-extension-component-section': true,
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
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    it('should show deprecated tabs when the major feature flag is inactive', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({
            name: 'sw-tabs-deprecated__wrapped',
        });
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
        expect(wrapper.findComponent({ name: 'mt-tabs' }).exists()).toBe(false);
    });

    it('should show meteor tabs when the major feature flag is active', async () => {
        wrapper = await createWrapper({
            featureActive: true,
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });

        expect(tabs.props('positionIdentifier')).toBe('sw-settings-usage-data');
        expect(tabs.props('defaultItem')).toBe('sw.settings.usage.data.index.general');
        expect(tabs.props('items')).toEqual([
            expect.objectContaining({
                label: 'sw-settings-usage-data-general.tabHeadline',
                name: 'sw.settings.usage.data.index.general',
                onClick: expect.any(Function),
            }),
        ]);
        expect(
            wrapper
                .findComponent({
                    name: 'sw-tabs-deprecated__wrapped',
                })
                .exists(),
        ).toBe(false);
    });

    it('should navigate when a meteor tab item is clicked', async () => {
        const routerPush = jest.fn();
        wrapper = await createWrapper({
            featureActive: true,
            routerPush,
        });
        await flushPromises();

        const tabs = wrapper.getComponent({ name: 'mt-tabs' });
        const generalTab = tabs.props('items')[0];

        generalTab.onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.usage.data.index.general' });
    });
});
