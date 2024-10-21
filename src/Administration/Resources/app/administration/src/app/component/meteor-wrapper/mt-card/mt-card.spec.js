/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('src/app/component/meteor-wrapper/mt-card', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = mount(await wrapTestComponent('mt-card', { sync: true }), {
            props: {
                positionIdentifier: 'demo',
            },
            global: {
                stubs: {
                    'sw-extension-component-section': true,
                },
            },
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render the extension component sections by default when positionIdentifier is set', async () => {
        const wrapper = mount(await wrapTestComponent('mt-card', { sync: true }), {
            props: {
                positionIdentifier: 'demo',
            },
            global: {
                stubs: {
                    'sw-extension-component-section': true,
                },
            },
        });

        expect(wrapper.find('sw-extension-component-section-stub').exists()).toBe(true);
        expect(wrapper.find('sw-extension-component-section-stub[position-identifier="demo__before"]').exists()).toBe(true);
        expect(wrapper.find('sw-extension-component-section-stub[position-identifier="demo__after"]').exists()).toBe(true);
    });

    it('should not render the extension component sections by default when positionIdentifier is undefined', async () => {
        // The position identifier is a required prop therefore expect this warning
        global.allowedErrors.push({
            method: 'warn',
            msgCheck: (msg) => {
                if (typeof msg !== 'string') {
                    return false;
                }

                return msg.includes('Missing required prop: "positionIdentifier"');
            },
        });

        const wrapper = mount(await wrapTestComponent('mt-card', { sync: true }), {
            global: {
                stubs: {
                    'sw-extension-component-section': true,
                },
            },
        });

        expect(wrapper.find('sw-extension-component-section').exists()).toBe(false);
        expect(wrapper.find('sw-extension-component-section[position-identifier="demo__before"]').exists()).toBe(false);
        expect(wrapper.find('sw-extension-component-section[position-identifier="demo__after"]').exists()).toBe(false);
    });
});
