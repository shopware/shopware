/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-context-button', { sync: true }), {
        global: {
            stubs: {
                'sw-icon': true,
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'sw-popover': {
                    template: `
                        <div class="sw-popover">
                            <slot></slot>
                        </div>
                    `,
                },
            },
        },
        ...customOptions,
    });
}

describe('src/app/component/context-menu/sw-context-button', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should open the context menu on click', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();

        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();
        expect(wrapper.find('.sw-context-menu').isVisible()).toBeTruthy();
        expect(wrapper.emitted('on-open-change')[0]).toEqual([true]);
    });

    it('should close the context menu', async () => {
        const wrapper = await createWrapper({
            props: {
                showMenuOnStartup: true,
            },
        });
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();

        await wrapper.trigger('click');

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();
        expect(wrapper.emitted('on-open-change')[0]).toEqual([false]);
    });

    it('should not open the context menu on click', async () => {
        const wrapper = await createWrapper({
            props: {
                disabled: true,
            },
        });

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();

        await wrapper.trigger('click');

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();
    });
});
