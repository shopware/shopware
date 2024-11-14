/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

describe('components/base/sw-alert-deprecated', () => {
    let wrapper;

    it('should be a Vue.js component', async () => {
        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            global: {
                stubs: ['sw-icon'],
            },
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        const title = 'Alert title';
        const message = '<p>Alert message</p>';

        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            global: {
                stubs: ['sw-icon'],
            },
            props: {
                title,
            },
            slots: {
                default: message,
            },
        });

        expect(wrapper.get('.sw-alert__title').text()).toBe(title);
        expect(wrapper.get('.sw-alert__message').html()).toContain(message);
    });

    it('should use custom icon', async () => {
        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            global: {
                stubs: ['sw-icon'],
            },
            props: {
                icon: 'your-icon-here',
            },
        });

        expect(wrapper.get('sw-icon-stub').attributes('name')).toBe('your-icon-here');
    });

    it.each([
        [
            'info',
            'default',
            true,
        ],
        [
            'warning',
            'default',
            true,
        ],
        [
            'error',
            'default',
            true,
        ],
        [
            'success',
            'default',
            true,
        ],
        [
            'info',
            'notification',
            true,
        ],
        [
            'warning',
            'notification',
            true,
        ],
        [
            'error',
            'notification',
            true,
        ],
        [
            'success',
            'notification',
            true,
        ],
        [
            'info',
            'system',
            false,
        ],
        [
            'warning',
            'system',
            false,
        ],
        [
            'error',
            'system',
            false,
        ],
        [
            'success',
            'system',
            false,
        ],
        [
            'neutral',
            'default',
            true,
        ],
        [
            'neutral',
            'notification',
            true,
        ],
        [
            'neutral',
            'system',
            false,
        ],
    ])('applies variant class %s to %s is %s', async (variant, appearance, applied) => {
        wrapper = mount(await wrapTestComponent('sw-alert-deprecated', { sync: true }), {
            global: {
                stubs: ['sw-icon'],
            },
            props: {
                appearance: appearance,
                variant: variant,
            },
        });

        expect(wrapper.get('.sw-alert').classes(`sw-alert--${variant}`)).toBe(applied);
    });
});
