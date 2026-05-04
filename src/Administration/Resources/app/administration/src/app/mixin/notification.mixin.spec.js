/**
 * @sw-package framework
 */
import 'src/app/mixin/notification.mixin';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

async function createWrapper() {
    return mount(
        {
            template: `
            <div class="sw-mock">
              <slot></slot>
            </div>
        `,
            mixins: [
                Shopware.Mixin.getByName('notification'),
            ],
            data() {
                return {
                    name: 'sw-mock-field',
                };
            },
        },
        {
            attachTo: document.body,
        },
    );
}

describe('src/app/mixin/notification.mixin.ts', () => {
    let wrapper;
    let createNotificationSpy;

    beforeEach(async () => {
        wrapper = await createWrapper();
        setActivePinia(createPinia());
        createNotificationSpy = jest.spyOn(Shopware.Store.get('notification'), 'createNotification');
        await flushPromises();
    });

    it('should dispatch a notification on createNotification', () => {
        wrapper.vm.createNotification({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
        });
    });

    it('should dispatch a notification on createNotificationSuccess with translation key', () => {
        wrapper.vm.createNotificationSuccess({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'success',
            title: 'global.default.success',
        });
    });

    it('should dispatch a notification on createNotificationSuccess without translating title', () => {
        wrapper.vm.createNotificationSuccess({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'global.default.success', // Should be the key, not translated
            }),
        );
    });

    it('should dispatch a notification on createNotificationSuccess with custom title', () => {
        wrapper.vm.createNotificationSuccess({
            message: 'The unique message',
            title: 'Custom success title',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'success',
            title: 'Custom success title',
        });
    });

    it('should dispatch a notification on createNotificationInfo with translation key', () => {
        wrapper.vm.createNotificationInfo({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'info',
            title: 'global.default.info',
        });
    });

    it('should dispatch a notification on createNotificationInfo without translating title', () => {
        wrapper.vm.createNotificationInfo({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'global.default.info', // Should be the key, not translated
            }),
        );
    });

    it('should dispatch a notification on createNotificationInfo with custom title', () => {
        wrapper.vm.createNotificationInfo({
            message: 'The unique message',
            title: 'Custom info title',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'info',
            title: 'Custom info title',
        });
    });

    it('should dispatch a notification on createNotificationWarning with translation key', () => {
        wrapper.vm.createNotificationWarning({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'warning',
            title: 'global.default.warning',
        });
    });

    it('should dispatch a notification on createNotificationWarning without translating title', () => {
        wrapper.vm.createNotificationWarning({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'global.default.warning', // Should be the key, not translated
            }),
        );
    });

    it('should dispatch a notification on createNotificationWarning with custom title', () => {
        wrapper.vm.createNotificationWarning({
            message: 'The unique message',
            title: 'Custom warning title',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'warning',
            title: 'Custom warning title',
        });
    });

    it('should dispatch a notification on createNotificationError with translation key', () => {
        wrapper.vm.createNotificationError({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'error',
            title: 'global.default.error',
        });
    });

    it('should dispatch a notification on createNotificationError without translating title', () => {
        wrapper.vm.createNotificationError({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith(
            expect.objectContaining({
                title: 'global.default.error', // Should be the key, not translated
            }),
        );
    });

    it('should dispatch a notification on createNotificationError with custom title', () => {
        wrapper.vm.createNotificationError({
            message: 'The unique message',
            title: 'Custom error title',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'error',
            title: 'Custom error title',
        });
    });

    it('should dispatch a notification on createSystemNotificationSuccess', () => {
        wrapper.vm.createSystemNotificationSuccess({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'success',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationInfo', () => {
        wrapper.vm.createSystemNotificationInfo({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'info',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationWarning', () => {
        wrapper.vm.createSystemNotificationWarning({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'warning',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationError', () => {
        wrapper.vm.createSystemNotificationError({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'error',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotification', () => {
        wrapper.vm.createSystemNotification({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            system: true,
        });
    });
});
