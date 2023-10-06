/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/base/sw-button';

describe('components/base/sw-button', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-button'));
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render a plain button', async () => {
        const label = 'Button text';
        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            slots: {
                default: label,
            },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe(label);
    });

    it('should render a plain button visible to screen readers', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            slots: { default: 'Screen reader button text' },
            propsData: { role: 'button' },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe('Screen reader button text');
        const button = wrapper.find('button');
        expect(button).toBeTruthy();
        expect(button.attributes('role')).toBe('button');
    });

    it('should render a download button with a custom file name', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            propsData: {
                download: 'My filename.txt',
                link: 'http://my.download.link',
            },
        });
        const anchor = wrapper.find('a');
        expect(anchor).toBeTruthy();
        expect(anchor.attributes('href')).toBe('http://my.download.link');
        expect(anchor.attributes('download')).toBe('My filename.txt');
    });

    it('should render a relative router-link button', async () => {
        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            propsData: {
                routerLink: { path: 'some/relative/link' },
                append: true,
            },
            slots: { default: 'Router-link text' },
            stubs: ['router-link'],
        });
        const routerLink = wrapper.find('router-link-stub');
        expect(routerLink).toBeTruthy();
        expect(routerLink.attributes('append')).toBe('true');
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe('Router-link text');
    });

    it('should trigger an click event when the button is clicked', async () => {
        const click = jest.fn();

        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            slots: { default: 'I am clickable' },
            listeners: {
                click,
            },
        });

        await wrapper.trigger('click');
        expect(click).toHaveBeenCalled();
    });

    it('should not trigger an event when disabled', async () => {
        const click = jest.fn();

        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            propsData: {
                disabled: true,
            },
            slots: { default: 'I am clickable' },
            listeners: {
                click,
            },
        });

        await wrapper.trigger('click');
        expect(click).not.toHaveBeenCalled();
    });

    it('should not trigger an event if html5 disabled is removed', async () => {
        const click = jest.fn();

        const wrapper = shallowMount(await Shopware.Component.build('sw-button'), {
            propsData: {
                disabled: true,
            },
            slots: { default: 'I am clickable' },
            listeners: {
                click,
            },
        });

        const button = wrapper.find('button');
        expect(button.attributes('disabled')).toBe('disabled');

        button.element.removeAttribute('disabled');

        expect(button.attributes('disabled')).toBeFalsy();

        await wrapper.trigger('click');
        expect(click).not.toHaveBeenCalled();
    });
});
