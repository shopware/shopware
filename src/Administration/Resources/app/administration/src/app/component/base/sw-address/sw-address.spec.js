import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-address/index';

async function createWrapper(propsData = {}) {
    return shallowMount(await Shopware.Component.build('sw-address'), {
        propsData
    });
}

describe('components/base/sw-address', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the formatting address', async () => {
        global.activeFeatureFlags = ['v6.5.0.0'];

        const wrapper = await createWrapper({
            formattingAddress: 'Christa Stracke<br> \\n \\n Philip Inlet<br> \\n \\n \\n \\n 22005-3637 New Marilyneside<br> \\n \\n Moldova (Republic of)<br><br>',
        });

        const formattingAddress = wrapper.find('.sw-address__formatting');

        expect(formattingAddress).toBeTruthy();
        expect(formattingAddress.text()).toBe('Christa Stracke \\n \\n Philip Inlet \\n \\n \\n \\n 22005-3637 New Marilyneside \\n \\n Moldova (Republic of)');
    });
});
