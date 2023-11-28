import { mount } from '@vue/test-utils_v3';

/**
 * @package customer-order
 */
async function createWrapper() {
    return mount(await wrapTestComponent('sw-settings-country-preview-template', {
        sync: true,
    }), {
        propsData: {
            formattingAddress: 'Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)',
        },
    });
}

describe('module/sw-settings-country/component/sw-settings-country-preview-template', () => {
    it('should be rendering template', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-settings-country-preview-template > div').html()).toBe(
            '<div>Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)</div>',
        );
    });
});
