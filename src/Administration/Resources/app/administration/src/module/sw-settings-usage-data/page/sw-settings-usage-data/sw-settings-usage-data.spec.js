import { mount } from '@vue/test-utils';

/**
 * @sw-package framework
 */
async function createWrapper() {
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
                    'mt-tabs': {
                        props: ['items', 'defaultItem'],
                        template: '<mt-tabs-stub :items="items" :default-item="defaultItem" />',
                    },
                    'router-view': true,
                    'sw-search-bar': true,
                    'sw-tabs-item': true,
                    'sw-error-summary': true,
                    'sw-extension-component-section': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    beforeEach(() => {
        global.activeFeatureFlags = [''];
    });

    it('should show legacy tabs when major tabs migration is inactive', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({
            name: 'sw-tabs-deprecated__wrapped',
        });
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
        expect(wrapper.find('mt-tabs-stub').exists()).toBe(false);
    });

    it('should show mt-tabs with usage data tab items when major tabs migration is active', async () => {
        global.activeFeatureFlags = ['V6_8_0_0'];

        wrapper = await createWrapper();
        await flushPromises();

        const mtTabs = wrapper.getComponent('mt-tabs-stub');

        expect(mtTabs.props('items')).toStrictEqual([
            {
                label: 'sw-settings-usage-data-general.tabHeadline',
                name: 'general',
                route: { name: 'sw.settings.usage.data.index.general' },
                onClick: expect.any(Function),
            },
        ]);
        expect(mtTabs.props('defaultItem')).toBe('general');
        expect(wrapper.findComponent({ name: 'sw-tabs-deprecated__wrapped' }).exists()).toBe(false);
    });
});
