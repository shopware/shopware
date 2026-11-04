/**
 * @sw-package framework
 */
import { mount } from '@vue/test-utils';

async function createWrapper({ save = jest.fn(() => Promise.resolve()) } = {}) {
    const wrapper = mount(await wrapTestComponent('sw-settings-webhook-secret-modal', { sync: true }), {
        props: { webhook: { id: 'webhook-id', name: 'Order placed' } },
        global: {
            provide: {
                repositoryFactory: {
                    create: () => ({
                        create: (context: unknown, id: string) => ({ id, _isNew: true }),
                        save,
                    }),
                },
            },
            stubs: {
                'mt-modal-root': {
                    template: '<div class="mt-modal-root"><slot></slot></div>',
                },
                'mt-modal': {
                    template: `
                        <div class="mt-modal">
                            <slot name="default"></slot>
                            <div class="mt-modal__footer"><slot name="footer"></slot></div>
                        </div>`,
                },
                'mt-text-field': {
                    props: ['modelValue'],
                    emits: ['update:modelValue'],
                    template: `
                        <input
                            class="mt-text-field"
                            :value="modelValue"
                            @input="$emit('update:modelValue', $event.target.value)"
                        />`,
                },
                'mt-banner': {
                    template: '<div class="mt-banner"><slot></slot></div>',
                },
                'mt-button': {
                    props: ['disabled'],
                    emits: ['click'],
                    template:
                        '<button class="mt-button" :disabled="disabled" @click="$emit(\'click\')"><slot></slot></button>',
                },
            },
        },
    });

    await flushPromises();

    const createNotification = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');

    return { wrapper, createNotification, save };
}

describe('src/module/sw-settings-webhook/component/sw-settings-webhook-secret-modal', () => {
    it('generates a random 256 bit secret', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.sw-settings-webhook-secret-modal__generate').trigger('click');

        expect((wrapper.find('.mt-text-field').element as HTMLInputElement).value).toMatch(/^[0-9a-f]{64}$/);
    });

    it('saves only the entered secret on the existing webhook and closes', async () => {
        const { wrapper, createNotification, save } = await createWrapper();
        await wrapper.find('.mt-text-field').setValue('secret-of-the-receiver');

        await wrapper.find('.sw-settings-webhook-secret-modal__save').trigger('click');
        await flushPromises();

        expect(save).toHaveBeenCalledWith({ id: 'webhook-id', _isNew: false, secret: 'secret-of-the-receiver' });
        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'success', message: 'sw-settings-webhook.secret.messageSaveSuccess' }),
        );
        expect(wrapper.emitted('modal-close')).toHaveLength(1);
    });

    it('cannot save a secret of only spaces', async () => {
        const { wrapper } = await createWrapper();

        await wrapper.find('.mt-text-field').setValue('   ');

        expect(wrapper.find('.sw-settings-webhook-secret-modal__save').attributes('disabled')).toBeDefined();
    });

    it('stays open and shows an error when saving fails', async () => {
        const { wrapper, createNotification } = await createWrapper({
            save: jest.fn(() => Promise.reject(new Error('refused'))),
        });
        await wrapper.find('.mt-text-field').setValue('secret-of-the-receiver');

        await wrapper.find('.sw-settings-webhook-secret-modal__save').trigger('click');
        await flushPromises();

        expect(createNotification).toHaveBeenCalledWith(
            expect.objectContaining({ variant: 'error', message: 'sw-settings-webhook.secret.messageSaveError' }),
        );
        expect(wrapper.emitted('modal-close')).toBeUndefined();
    });
});
