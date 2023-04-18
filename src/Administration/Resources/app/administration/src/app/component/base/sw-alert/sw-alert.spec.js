/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-alert';

describe('components/base/sw-alert', () => {
    let wrapper;

    afterEach(() => { if (wrapper) wrapper.destroy(); });

    it('should be a Vue.js component', async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon'],
        });
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render correctly', async () => {
        const title = 'Alert title';
        const message = '<p>Alert message</p>';

        wrapper = shallowMount(await Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon'],
            propsData: {
                title,
            },
            slots: {
                default: message,
            },
        });

        expect(wrapper.element).toMatchSnapshot();
    });

    it('should use custom icon', async () => {
        wrapper = shallowMount(await Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon'],
            propsData: {
                icon: 'your-icon-here',
            },
        });

        expect(wrapper.element).toMatchSnapshot();
    });

    it.each([
        ['info', 'default', true],
        ['warning', 'default', true],
        ['error', 'default', true],
        ['success', 'default', true],
        ['info', 'notification', true],
        ['warning', 'notification', true],
        ['error', 'notification', true],
        ['success', 'notification', true],
        ['info', 'system', false],
        ['warning', 'system', false],
        ['error', 'system', false],
        ['success', 'system', false],
        ['neutral', 'default', true],
        ['neutral', 'notification', true],
        ['neutral', 'system', false],
    ])('applies variant class %s to %s is %s', async (variant, appearance, applied) => {
        wrapper = shallowMount(await Shopware.Component.build('sw-alert'), {
            stubs: ['sw-icon'],
            propsData: {
                appearance: appearance,
                variant: variant,
            },
        });

        expect(wrapper.classes(`sw-alert--${variant}`)).toBe(applied);
    });
});

