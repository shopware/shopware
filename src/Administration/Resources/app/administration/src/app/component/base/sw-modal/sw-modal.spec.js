/**
 * @package admin
 */

import 'src/app/component/base/sw-modal';
import { shallowMount } from '@vue/test-utils';

async function createWrapper() {
    return shallowMount(await Shopware.Component.build('sw-modal'), {
        stubs: {
            'sw-icon': true,
        },
        provide: {
            shortcutService: {
                startEventListener: () => {},
                stopEventListener: () => {},
            },
        },
        attachTo: document.body,
        slots: {
            default: `
                <div class="modal-content-stuff">
                    Some content inside modal body
                    <input name="test" class="test-input">
                </div>
            `,
        },
    });
}

describe('src/app/component/base/sw-modal/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();

        await flushPromises();
    });

    afterEach(async () => {
        if (wrapper) {
            await wrapper.destroy();
        }

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should render modal with body', async () => {
        await wrapper.setProps({
            title: 'Cool modal',
        });

        expect(wrapper.get('.sw-modal__body').text()).toBe('Some content inside modal body');
        expect(wrapper.get('.sw-modal__title').text()).toBe('Cool modal');
    });

    it('should show console error when using invalid variant', async () => {
        const consoleSpy = jest.spyOn(console, 'error').mockImplementation();

        await wrapper.setProps({
            variant: 'not-existing', // Make some trouble
        });

        expect(consoleSpy).toHaveBeenCalledTimes(1);
    });

    it.each([
        'default',
        'small',
        'large',
        'full',
    ])('should set correct variant class for %s', async (variant) => {
        await wrapper.setProps({
            variant: variant,
        });

        expect(wrapper.get('.sw-modal').classes(`sw-modal--${variant}`)).toBe(true);
    });

    it('should have has--header class if showHeader option is true', async () => {
        await wrapper.setProps({
            showHeader: true,
        });

        expect(wrapper.get('.sw-modal__dialog').classes('has--header')).toBe(true);
    });

    it('should not have has--header class if showHeader option is false', async () => {
        await wrapper.setProps({
            showHeader: false,
        });

        expect(wrapper.get('.sw-modal__dialog').classes('has--header')).toBe(false);
    });

    it('should fire the close event when clicking the close button', async () => {
        await wrapper.get('.sw-modal__close').trigger('click');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should close the modal when clicking outside modal', async () => {
        await wrapper.get('.sw-modal').trigger('mousedown');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not close the modal when clicking outside modal and closeable option is false', async () => {
        await wrapper.setProps({
            closable: false,
        });

        await wrapper.get('.sw-modal').trigger('mousedown');

        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });

    it('should close the modal when using ESC key', async () => {
        await wrapper.get('.sw-modal__dialog').trigger('keyup.esc');

        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('should not close the modal when using ESC key when the event does not come from the modal dialog', async () => {
        await wrapper.get('.test-input').trigger('keyup.esc');

        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });
});
