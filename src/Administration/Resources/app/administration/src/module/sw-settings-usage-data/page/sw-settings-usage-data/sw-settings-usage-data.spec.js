import { mount } from '@vue/test-utils';
import swSettingsUsageData from './index';

/**
 * @sw-package framework
 */
function createTabs() {
    const routerPush = jest.fn(() => Promise.resolve());
    const tabs = swSettingsUsageData.computed.tabs.call({
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

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-usage-data', {
            sync: true,
        }),
        {
            global: {
                mocks: {
                    feature: {
                        isActive: jest.fn(() => false),
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
                    'mt-tabs': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    it('should show tabs', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const tabs = wrapper.findComponent({
            name: 'sw-tabs-deprecated__wrapped',
        });
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
    });

    it('builds mt-tabs route items', () => {
        const { tabs } = createTabs();

        expect(tabs).toEqual([
            expect.objectContaining({
                label: 'sw-settings-usage-data-general.tabHeadline',
                name: 'sw.settings.usage.data.index.general',
                onClick: expect.any(Function),
            }),
        ]);
    });

    it('pushes the matching usage data route when a tab is clicked', () => {
        const { routerPush, tabs } = createTabs();

        tabs[0].onClick();

        expect(routerPush).toHaveBeenCalledWith({ name: 'sw.settings.usage.data.index.general' });
    });
});
