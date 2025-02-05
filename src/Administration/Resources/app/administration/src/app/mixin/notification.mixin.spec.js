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

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should dispatch a notification on createNotification', () => {
        wrapper.vm.createNotification({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
        });
    });

    it('should dispatch a notification on createNotificationSuccess', () => {
        wrapper.vm.createNotificationSuccess({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'success',
            title: 'global.default.success',
        });
    });

    it('should dispatch a notification on createNotificationInfo', () => {
        wrapper.vm.createNotificationInfo({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'info',
            title: 'global.default.info',
        });
    });

    it('should dispatch a notification on createNotificationWarning', () => {
        wrapper.vm.createNotificationWarning({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'warning',
            title: 'global.default.warning',
        });
    });

    it('should dispatch a notification on createNotificationError', () => {
        wrapper.vm.createNotificationError({
            message: 'The unique message',
        });

        expect(createNotificationSpy).toHaveBeenCalledWith({
            message: 'The unique message',
            variant: 'error',
            title: 'global.default.error',
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
