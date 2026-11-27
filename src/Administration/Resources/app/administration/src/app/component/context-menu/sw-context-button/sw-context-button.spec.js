/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

const floatingUiStub = {
    props: [
        'isOpened',
        'floatingUiOptions',
    ],
    template: '<div><slot v-if="isOpened"></slot></div>',
};

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-context-button', { sync: true }), {
        global: {
            stubs: {
                'sw-context-menu': await wrapTestComponent('sw-context-menu'),
                'mt-floating-ui': floatingUiStub,
            },
        },
        ...customOptions,
    });
}

describe('src/app/component/context-menu/sw-context-button', () => {
    it('should open the context menu on click', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();

        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();
        expect(wrapper.find('.sw-context-menu').isVisible()).toBeTruthy();
        expect(wrapper.emitted('on-open-change')[0]).toEqual([true]);
    });

    it('mounts the floating menu only while it is open', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.findComponent(floatingUiStub).exists()).toBe(false);

        await wrapper.trigger('click');

        expect(wrapper.findComponent(floatingUiStub).exists()).toBe(true);
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

    it('should close the context menu on outside click before propagation is stopped', async () => {
        const wrapper = await createWrapper({
            props: {
                autoCloseOutsideClick: true,
            },
        });
        await flushPromises();

        await wrapper.trigger('click');
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBeTruthy();

        const outsideButton = document.createElement('button');
        const stopClickPropagation = (event) => {
            event.stopPropagation();
        };

        outsideButton.addEventListener('click', stopClickPropagation);
        document.body.appendChild(outsideButton);

        outsideButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(wrapper.find('.sw-context-menu').exists()).toBeFalsy();
        expect(wrapper.emitted('on-open-change')).toEqual([[true], [false]]);

        outsideButton.removeEventListener('click', stopClickPropagation);
        outsideButton.remove();
    });

    it('should remove click listeners before unmounting', async () => {
        const removeEventListenerSpy = jest.spyOn(document, 'removeEventListener');
        const wrapper = await createWrapper({
            props: {
                autoCloseOutsideClick: true,
            },
        });

        await wrapper.trigger('click');
        await flushPromises();

        const { handleOutsideClickEvent, handleClickEvent } = wrapper.vm;

        wrapper.unmount();

        expect(removeEventListenerSpy).toHaveBeenCalledWith('click', handleOutsideClickEvent, true);
        expect(removeEventListenerSpy).toHaveBeenCalledWith('click', handleClickEvent);

        removeEventListenerSpy.mockRestore();
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

    it.each([
        ['bottom-start', 190, 'bottom-start'],
        ['bottom-end', 110, 'bottom-end'],
        ['top-end', 110, 'top-end'],
    ])('keeps the menu arrow under the button centre when placed %s', async (placement, expectedX, expectedPlacement) => {
        const wrapper = await createWrapper();
        await wrapper.trigger('click');
        const { middleware } = wrapper.getComponent(floatingUiStub).props('floatingUiOptions');
        const floating = document.createElement('div');

        const result = middleware[0].fn({
            x: 150,
            placement,
            rects: { reference: { width: 150 } },
            elements: { floating },
        });

        expect(result).toEqual({ x: expectedX });
        expect(floating.dataset.placement).toBe(expectedPlacement);
    });

    it('starts above the button when the menu is aligned to the top', async () => {
        const wrapper = await createWrapper({ props: { menuVerticalAlign: 'top', menuHorizontalAlign: 'left' } });
        await wrapper.trigger('click');

        expect(wrapper.getComponent(floatingUiStub).props('floatingUiOptions').placement).toBe('top-end');
    });
});
