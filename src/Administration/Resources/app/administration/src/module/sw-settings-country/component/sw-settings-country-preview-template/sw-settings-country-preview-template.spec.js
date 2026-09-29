import { mount } from '@vue/test-utils';

/**
 * @sw-package fundamentals@discovery
 */
async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-country-preview-template', {
            sync: true,
        }),
        {
            props: {
                formattingAddress:
                    'Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)',
                isLoading: true,
            },
            global: {
                stubs: {
                    'sw-loader': true,
                },
            },
        },
    );
}

describe('module/sw-settings-country/component/sw-settings-country-preview-template', () => {
    it('should be rendering template', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-settings-country-preview-template__content').html()).toBe(
            '<div class="sw-settings-country-preview-template__content">Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)</div>',
        );
        expect(wrapper.find('sw-loader-stub').exists()).toBe(true);
        expect(wrapper.attributes('aria-busy')).toBe('true');
    });
});
