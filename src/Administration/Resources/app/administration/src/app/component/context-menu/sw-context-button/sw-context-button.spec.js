/**
 * @package admin
 */

import { shallowMount } from '@vue/test-utils';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/context-menu/sw-context-menu';

async function createWrapper(customOptions = {}) {
    return shallowMount(await Shopware.Component.build('sw-context-button'), {
        stubs: {
            'sw-icon': true,
            'sw-context-menu': await Shopware.Component.build('sw-context-menu'),
            'sw-popover': true,
        },
        slots: {
            default: '<div class="context-menu-item"></div>',
        },
        provide: {},
        mocks: {},
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

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();
        expect(wrapper.find('.sw-context-menu').isVisible()).toBeTruthy();
        expect(wrapper.emitted('on-open-change')[0]).toEqual([true]);
    });

    it('should close the context menu', async () => {
        const wrapper = await createWrapper({
            propsData: {
                showMenuOnStartup: true,
            },
        });

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();

        await wrapper.trigger('click');

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();
        expect(wrapper.emitted('on-open-change')[0]).toEqual([false]);
    });

    it('should not open the context menu on click', async () => {
        const wrapper = await createWrapper({
            propsData: {
                disabled: true,
            },
        });

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();

        await wrapper.trigger('click');

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();
    });
});
