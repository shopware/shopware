/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(customOptions = {}) {
    return mount(await wrapTestComponent('sw-context-button', { sync: true }), {
        global: {
            stubs: {
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
        expect(wrapper.emitted('on-open-change')).toEqual([
            [true],
            [false],
        ]);

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
});
