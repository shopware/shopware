/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-status', { sync: true }), {
        global: {
            stubs: {
                'sw-color-badge': true,
            },
        },
        ...customOptions,
    });
}

describe('src/app/component/utils/sw-status', () => {
    /** @type Wrapper */
    let wrapper;

    it('should be a Vue.JS component', async () => {
        wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the color green', async () => {
        wrapper = await createWrapper({
            props: { color: 'green' },
        });

        expect(wrapper.classes()).toContain('sw-status--green');
    });

    it('should render the color red', async () => {
        wrapper = await createWrapper({
            props: { color: 'red' },
        });

        expect(wrapper.classes()).toContain('sw-status--red');
    });

    it('should render the content of the slot', async () => {
        wrapper = await createWrapper({
            slots: {
                default: '<h1>Hello from the slot</h1>',
            },
        });

        expect(wrapper.text()).toContain('Hello from the slot');
    });

    it('should render the color badge', async () => {
        wrapper = await createWrapper({
            props: { color: 'red' },
        });

        const colorBadge = await wrapper.find('sw-color-badge-stub');
        expect(colorBadge.isVisible()).toBe(true);
    });
});
