import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-country/component/sw-settings-country-preview-template';

function createWrapper() {
    return shallowMount(Shopware.Component.build('sw-settings-country-preview-template'), {
        propsData: {
            formattingAddress: 'Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)',
        }
    });
}

describe('module/sw-settings-country/component/sw-settings-country-preview-template', () => {
    it('should be rendering template', () => {
        const wrapper = createWrapper();

        expect(wrapper.find('.sw-settings-country-preview-template > div').html()).toEqual(
            '<div>Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)</div>'
        );
    });
});
