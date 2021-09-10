import { shallowMount } from '@vue/test-utils';
import 'src/app/component/utils/sw-status';

function createWrapper(customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-status'), {
        stubs: {
            'sw-color-badge': true
        },
        ...customOptions
    });
}

describe('src/app/component/utils/sw-status', () => {
    /** @type Wrapper */
    let wrapper;

    afterEach(async () => {
        if (wrapper) await wrapper.destroy();
    });

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the color green', async () => {
        wrapper = await createWrapper({
            propsData: { color: 'green' }
        });

        expect(wrapper.classes()).toContain('sw-status--green');
    });

    it('should render the color red', async () => {
        wrapper = await createWrapper({
            propsData: { color: 'red' }
        });

        expect(wrapper.classes()).toContain('sw-status--red');
    });

    it('should render the content of the slot', async () => {
        wrapper = await createWrapper({
            slots: {
                default: '<h1>Hello from the slot</h1>'
            }
        });

        expect(wrapper.text()).toContain('Hello from the slot');
    });

    it('should render the color badge', async () => {
        wrapper = await createWrapper({
            propsData: { color: 'red' }
        });

        expect(wrapper.find('sw-color-badge-stub').isVisible()).toBe(true);
    });
});
