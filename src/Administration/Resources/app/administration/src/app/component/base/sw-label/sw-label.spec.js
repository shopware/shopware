/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper(propsData = {}) {
    return mount(await wrapTestComponent('sw-label', { sync: true }), {
        global: {
            stubs: {
                'sw-color-badge': true,
            },
        },
        props: propsData,
    });
}

describe('src/app/component/base/sw-label', () => {
    it('should be dismissable', async () => {
        const wrapper = await createWrapper({
            dismissable: true,
            onDismiss: () => {},
        });

        expect(wrapper.find('.sw-label__dismiss').exists()).toBe(true);
    });

    it('should not be dismissable', async () => {
        const wrapper = await createWrapper({
            dismissable: false,
            onDismiss: () => {},
        });

        expect(wrapper.find('.sw-label__dismiss').exists()).toBe(false);
    });

    it('should prevent mousedown and emit dismiss only on click', async () => {
        const wrapper = await createWrapper({
            dismissable: true,
            onDismiss: () => {},
        });

        const dismissButton = wrapper.find('.sw-label__dismiss');
        const mousedownEvent = new MouseEvent('mousedown', {
            bubbles: true,
            cancelable: true,
        });

        dismissButton.element.dispatchEvent(mousedownEvent);

        await wrapper.vm.$nextTick();

        expect(mousedownEvent.defaultPrevented).toBe(true);
        expect(wrapper.emitted('dismiss')).toBeUndefined();

        await dismissButton.trigger('click');

        expect(wrapper.emitted('dismiss')).toHaveLength(1);
        expect(wrapper.emitted('selected')).toBeUndefined();
    });
});
