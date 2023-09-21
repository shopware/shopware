import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-preview-template';

/**
 * @package buyers-experience
 */
async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-settings-country-preview-template'), {
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
