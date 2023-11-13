/**
 * @package admin
 */

import { mount, RouterLinkStub } from '@vue/test-utils_v3';

describe('components/base/sw-button', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }));
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render a plain button', async () => {
        const label = 'Button text';
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            slots: {
                default: label,
            },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe(label);
    });

    it('should render a plain button visible to screen readers', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            slots: { default: 'Screen reader button text' },
            props: { role: 'button' },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe('Screen reader button text');
        const button = wrapper.find('button');
        expect(button).toBeTruthy();
        expect(button.attributes('role')).toBe('button');
    });

    it('should render a download button with a custom file name', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            props: {
                download: 'My filename.txt',
                link: 'http://my.download.link',
            },
        });
        const anchor = wrapper.find('a');
        expect(anchor).toBeTruthy();
        expect(anchor.attributes('href')).toBe('http://my.download.link');
        expect(anchor.attributes('download')).toBe('My filename.txt');
    });

    it('should render router-link button', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            props: {
                routerLink: { path: 'some/relative/link' },
            },
            slots: { default: 'Router-link text' },
            global: {
                stubs: {
                    RouterLink: RouterLinkStub,
                },
            },
        });
        const routerLink = wrapper.find('router-link-stub');
        expect(routerLink).toBeTruthy();
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe('Router-link text');
    });

    it('should trigger an click event when the button is clicked', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            slots: { default: 'I am clickable' },
        });

        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted().click).toStrictEqual(expect.any(Array));
        expect(wrapper.emitted().click).toHaveLength(1);
    });

    it('should not trigger an event when disabled', async () => {
        const click = jest.fn();

        const wrapper = mount(await wrapTestComponent('sw-button', { sync: true }), {
            props: {
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
        const onClick = jest.fn();
        const wrapper = mount({
            template: '<sw-button :disabled="disabled" @click="onClick">I am clickable</sw-button>',
            components: {
                'sw-button': await wrapTestComponent('sw-button', { sync: true }),
            },
            data() {
                return {
                    disabled: true,
                };
            },
            methods: {
                onClick,
            },
        });

        const button = wrapper.find('button');
        expect(button.attributes('disabled')).toBe('');

        button.element.removeAttribute('disabled');

        expect(button.attributes('disabled')).toBeFalsy();

        await button.trigger('click');
        expect(onClick).not.toHaveBeenCalled();
    });
});
