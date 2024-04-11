import { mount } from '@vue/test-utils';
import swSettingsUsageData from 'src/module/sw-settings-usage-data/page/sw-settings-usage-data';
import swSettingsUsageDataGeneral from 'src/module/sw-settings-usage-data/component/sw-settings-usage-data-general';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-usage-data', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-page': {
                    template: `
                        <div class="sw-page">
                            <slot name="smart-bar-header"></slot>
                            <slot name="smart-bar-actions"></slot>
                            <slot name="content"></slot>
                        </div>`,
                },
                'sw-card-view': await wrapTestComponent('sw-card-view'),
                'sw-button': await wrapTestComponent('sw-button'),
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated'),
                'sw-tabs': await wrapTestComponent('sw-tabs'),
                'router-view': true,
            },
        },
    });
}

describe('src/module/sw-settings-usage-data/page/sw-settings-usage-data', () => {
    let wrapper;

    beforeEach(async () => {
        Shopware.State.registerModule('usageData', swSettingsUsageData);
        Shopware.State.registerModule('usageData', swSettingsUsageDataGeneral);
    });

    it('should show tabs', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const swTabs = await wrapTestComponent('sw-tabs');
        const tabs = wrapper.findComponent(swTabs);
        expect(tabs.isVisible()).toBe(true);
        expect(tabs.vm.positionIdentifier).toBe('sw-settings-usage-data');
    });
});
