import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-usage-data-intro', {
        sync: true,
    }), {
        global: {
            stubs: {
                'sw-icon': await wrapTestComponent('sw-icon'),
                'sw-external-link': await wrapTestComponent('sw-external-link'),
            },
        },
    });
}

describe('src/app/component/usage-data/sw-settings-usage-data-intro', () => {
    let wrapper = null;

    it('should show the solid-analytics icon', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const icon = await wrapper.find('[data-testid="sw-icon__solid-analytics"]');
        expect(icon.exists()).toBe(true);
        expect(icon.isVisible()).toBe(true);
    });

    it('should provide some contextual information', async () => {
        wrapper = await createWrapper();
        await flushPromises();

        const contextualInformation = await wrapper.text();
        expect(contextualInformation).toContain('sw-settings-usage-data-intro.headline');
        expect(contextualInformation).toContain('sw-settings-usage-data-intro.description');
    });
});
