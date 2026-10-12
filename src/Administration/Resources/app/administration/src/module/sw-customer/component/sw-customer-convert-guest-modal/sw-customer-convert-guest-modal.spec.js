import { mount } from '@vue/test-utils';

/**
 * @sw-package checkout
 */

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-customer-convert-guest-modal', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-modal': await wrapTestComponent('sw-modal', {
                        sync: true,
                    }),
                },
                provide: {
                    shortcutService: {
                        stopEventListener: () => {},
                        startEventListener: () => {},
                    },
                    guestCustomerConvertService: {
                        sendMail: async () => {},
                        convert: async () => {},
                    },
                    loadCustomer: jest.fn(),
                },
            },
            props: {
                customer: {
                    id: 'customer-id',
                    email: 'test@test.com',
                },
            },
        },
    );
}

describe('module/sw-customer-convert-guest-modal', () => {
    let wrapper;

    it('can close modal', async () => {
        wrapper = await createWrapper();

        const closeButton = await wrapper.find('.sw-modal__close');
        expect(closeButton.exists()).toBe(true);

        await closeButton.trigger('click');

        await flushPromises();

        expect(wrapper.emitted('modal-close')).toBeDefined();
    });

    it('can send recovery email', async () => {
        wrapper = await createWrapper();

        const spy = jest.spyOn(wrapper.vm.guestCustomerConvertService, 'convert');

        await flushPromises();

        const buttons = await wrapper.findAll('.sw-customer-guest-convert-customer-modal__action');
        const button = buttons.find((btn) => btn.text().includes('sw-customer.convertGuest.mail.button'));

        expect(button.exists()).toBe(true);

        await button.trigger('click');

        await flushPromises();

        expect(spy).toHaveBeenCalled();
    });

    it('can set password', async () => {
        wrapper = await createWrapper();

        const spy = jest.spyOn(wrapper.vm.guestCustomerConvertService, 'convert');

        await flushPromises();

        const buttons = await wrapper.findAll('.sw-customer-guest-convert-customer-modal__action');
        const button = buttons.find((btn) => btn.text().includes('sw-customer.convertGuest.manual.button'));

        expect(button.exists()).toBe(true);

        const input = wrapper.find('.mt-password-field__input');

        await input.setValue('password');

        expect(input.element.value).toBe('password');

        await button.trigger('click');

        await flushPromises();

        expect(spy).toHaveBeenCalled();
    });

    it('cannot set password with empty input', async () => {
        wrapper = await createWrapper();

        const spy = jest.spyOn(wrapper.vm.guestCustomerConvertService, 'convert');

        await flushPromises();

        const buttons = await wrapper.findAll('.sw-customer-guest-convert-customer-modal__action');
        const button = buttons.find((btn) => btn.text().includes('sw-customer.convertGuest.manual.button'));

        expect(button.exists()).toBe(true);

        await button.trigger('click');

        await flushPromises();

        expect(spy).not.toHaveBeenCalled();
    });
});
