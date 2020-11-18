import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-alert';

describe('components/base/sw-alert', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = shallowMount(Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon']
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        const title = 'Alert title';
        const message = '<p>Alert message</p>';

        const wrapper = shallowMount(Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon'],
            props: {
                title
            },
            slots: {
                default: message
            }
        });
        expect(wrapper.element).toMatchSnapshot();
    });
});

