import { shallowMount } from '@vue/test-utils';
import 'src/app/component/usage-data/sw-settings-usage-data-intro';
import 'src/app/component/base/sw-icon';
import 'src/app/component/utils/sw-external-link';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-settings-usage-data-intro'), {
        stubs: {
            'sw-icon': await Shopware.Component.build('sw-icon'),
            'sw-external-link': await Shopware.Component.build('sw-external-link'),
        },
    });
}

describe('src/app/component/usage-data/sw-settings-usage-data-intro', () => {
    let wrapper = null;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    it('should show the solid-analytics icon', async () => {
        wrapper = await createWrapper();

        const icon = await wrapper.find('[data-testid="sw-icon__solid-analytics"]');
        expect(icon.exists()).toBe(true);
        expect(icon.isVisible()).toBe(true);
    });

    it('should provide some contextual information', async () => {
        wrapper = await createWrapper();

        const contextualInformation = await wrapper.text();
        expect(contextualInformation).toContain('sw-settings-usage-data-intro.headline');
        expect(contextualInformation).toContain('sw-settings-usage-data-intro.description');
    });
});
