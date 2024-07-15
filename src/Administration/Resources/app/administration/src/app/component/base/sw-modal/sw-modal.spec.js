/**
 * @package admin
 * group disabledCompat
 */

import { shallowMount } from '@vue/test-utils';

async function createWrapper(additionalSlots = null) {
    return shallowMount(await wrapTestComponent('sw-modal', { sync: true }), {
        attachTo: document.body,
        global: {
            provide: {
                shortcutService: {
                    startEventListener: () => {},
                    stopEventListener: () => {},
                    stubs: {
                        'sw-icon': true,
                    },
                },
            },
        },
        slots: {
            default: `
                <div class="modal-content-stuff">
                    Some content inside modal body
                    <input name="test" class="test-input">
                </div>
            `,
            ...additionalSlots,
        },
    });
}

describe('src/app/component/base/sw-modal/index.js', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper({
            'modal-footer': '<div class="modal-footer-stuff">Some content inside modal footer</div>',
        });

        await flushPromises();
    });

    afterEach(async () => {
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
        const swModal = await wrapTestComponent('sw-modal', { sync: true });
        const validator = swModal.props.variant.validator;

        expect(validator('default')).toBe(true);
        expect(validator('small')).toBe(true);
        expect(validator('large')).toBe(true);
        expect(validator('full')).toBe(true);
        expect(validator('not-existing')).toBe(false);
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

    it('should have sw-modal__footer class if showFooter option is true', async () => {
        await wrapper.setProps({
            showFooter: true,
        });

        expect(wrapper.get('.sw-modal__footer').exists()).toBeTruthy();
        expect(wrapper.get('.modal-footer-stuff').exists()).toBeTruthy();
    });

    it('should not have sw-modal__footer class if showFooter option is false', async () => {
        await wrapper.setProps({
            showFooter: false,
        });

        expect(wrapper.get('.sw-modal__dialog').classes('sw-modal__footer')).toBeFalsy();
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

    it('should render content from modal title slot', async () => {
        wrapper = await createWrapper({
            'modal-title': '<div class="custom-html">Custom HTML title</div>',
        });

        expect(wrapper.get('.sw-modal__titles').html()).toContain('<div class="custom-html">Custom HTML title</div>');
    });

    it('should be able to update the modal classes', async () => {
        expect(wrapper.get('.sw-modal').classes('sw-modal--has-sidebar')).toBe(false);

        Shopware.State.commit('adminHelpCenter/setShowHelpSidebar', true);
        await wrapper.vm.$nextTick();

        expect(wrapper.get('.sw-modal').classes('sw-modal--has-sidebar')).toBe(true);
    });

    it('should add classes for the modal body correctly', async () => {
        await wrapper.setProps({
            showFooter: false,
        });
        expect(wrapper.get('.sw-modal__body').classes('has--no-footer')).toBeTruthy();

        await wrapper.setProps({
            showFooter: true,
        });
        expect(wrapper.get('.sw-modal__body').classes('has--no-footer')).toBeFalsy();
    });
});
