/**
 * @package admin
 * group disabledCompat
 */

import { mount } from '@vue/test-utils';
import { createRouter, createWebHashHistory } from 'vue-router';

describe('components/base/sw-button-deprecated', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }));
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render a plain button', async () => {
        const label = 'Button text';
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            slots: {
                default: label,
            },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe(label);
    });

    it('should render a plain button visible to screen readers', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            slots: { default: 'Screen reader button text' },
            props: { role: 'button' },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });
        const slot = wrapper.find('.sw-button__content');
        expect(slot).toBeTruthy();
        expect(slot.text()).toBe('Screen reader button text');
        const button = wrapper.find('button');
        expect(button).toBeTruthy();
        expect(button.attributes('role')).toBe('button');
    });

    it('should render a download button with a custom file name', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            props: {
                download: 'My filename.txt',
                link: 'http://my.download.link',
            },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });
        const anchor = wrapper.find('a');
        expect(anchor).toBeTruthy();
        expect(anchor.attributes('href')).toBe('http://my.download.link');
        expect(anchor.attributes('download')).toBe('My filename.txt');
    });

    it('should render router-link button', async () => {
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            props: {
                routerLink: { path: 'some/relative/link' },
            },
            slots: { default: 'Router-link text' },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
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
        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            slots: { default: 'I am clickable' },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });

        await wrapper.get('button').trigger('click');
        expect(wrapper.emitted().click).toStrictEqual(expect.any(Array));
        expect(wrapper.emitted().click).toHaveLength(1);
    });

    it('should not trigger an event when disabled', async () => {
        const click = jest.fn();

        const wrapper = mount(await wrapTestComponent('sw-button-deprecated', { sync: true }), {
            props: {
                disabled: true,
            },
            slots: { default: 'I am clickable' },
            listeners: {
                click,
            },
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });

        await wrapper.trigger('click');
        expect(click).not.toHaveBeenCalled();
    });

    it('should not trigger an event if html5 disabled is removed', async () => {
        const onClick = jest.fn();
        const wrapper = mount({
            template: '<sw-button-deprecated :disabled="disabled" @click="onClick">I am clickable</sw-button-deprecated>',
            components: {
                'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
            },
            data() {
                return {
                    disabled: true,
                };
            },
            methods: {
                onClick,
            },
        }, {
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
            },
        });

        const button = wrapper.find('button');
        expect(button.attributes('disabled')).toBe('');

        button.element.removeAttribute('disabled');

        expect(button.attributes('disabled')).toBeFalsy();

        await button.trigger('click');
        expect(onClick).not.toHaveBeenCalled();
    });

    it('should not trigger a route navigation for disabled router link button', async () => {
        const router = createRouter({
            routes: [
                {
                    name: 'sw.dashboard.index',
                    path: '/sw/dashboard/index',
                },
                {
                    name: 'sw.order.index',
                    path: '/sw/order/list',
                },
            ],
            history: createWebHashHistory(),
        });

        await router.push({ name: 'sw.dashboard.index' });

        const wrapper = mount({
            template: '<sw-button-deprecated :disabled="true" :router-link="{ name: \'sw.order.index\' }">Disabled router link</sw-button-deprecated>',
        }, {
            global: {
                stubs: {
                    'sw-button-deprecated': await wrapTestComponent('sw-button-deprecated', { sync: true }),
                },
                plugins: [
                    router,
                ],
                mocks: {
                    $router: router,
                },
            },
        });

        expect(wrapper.vm.$router.currentRoute.value.name).toBe('sw.dashboard.index');

        const button = wrapper.find('.sw-button');
        await button.trigger('click');
        await flushPromises();

        expect(wrapper.vm.$router.currentRoute.value.name).toBe('sw.dashboard.index');
    });
});
