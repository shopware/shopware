import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-first-run-wizard/index';

describe('module/sw-first-run-wizard/view/sw-first-run-wizard-introduction', () => {
    it('renders the introduction component', async () => {
        const component = await Shopware.Component.build('sw-first-run-wizard-introduction');

        const wrapper = shallowMount(component, {
            global: {
                mocks: {
                    assetFilter: (value) => value,
                    $t: (key) => key,
                },
            },
        });

        expect(wrapper.find('.sw-first-run-wizard-introduction').exists()).toBe(true);

        wrapper.unmount();
    });
});
