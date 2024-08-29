/**
 * @package admin
 */
import 'src/app/mixin/notification.mixin';
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount({
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
    }, {
        attachTo: document.body,
    });
}

describe('src/app/mixin/notification.mixin.ts', () => {
    let wrapper;
    let originalDispatch;

    beforeEach(async () => {
        if (originalDispatch) {
            Object.defineProperty(Shopware.State, 'dispatch', {
                value: originalDispatch,
            });
        } else {
            originalDispatch = Shopware.State.dispatch;
        }

        wrapper = await createWrapper();

        await flushPromises();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should dispatch a notification on createNotification', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createNotification({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
        });
    });

    it('should dispatch a notification on createNotificationSuccess', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createNotificationSuccess({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'success',
            title: 'global.default.success',
        });
    });

    it('should dispatch a notification on createNotificationInfo', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createNotificationInfo({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'info',
            title: 'global.default.info',
        });
    });

    it('should dispatch a notification on createNotificationWarning', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createNotificationWarning({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'warning',
            title: 'global.default.warning',
        });
    });

    it('should dispatch a notification on createNotificationError', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createNotificationError({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'error',
            title: 'global.default.error',
        });
    });

    it('should dispatch a notification on createSystemNotificationSuccess', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createSystemNotificationSuccess({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'success',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationInfo', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createSystemNotificationInfo({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'info',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationWarning', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createSystemNotificationWarning({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'warning',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotificationError', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createSystemNotificationError({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            variant: 'error',
            system: true,
        });
    });

    it('should dispatch a notification on createSystemNotification', () => {
        Object.defineProperty(Shopware.State, 'dispatch', {
            value: jest.fn(),
        });

        wrapper.vm.createSystemNotification({
            message: 'The unique message',
        });

        expect(Shopware.State.dispatch).toHaveBeenCalledWith('notification/createNotification', {
            message: 'The unique message',
            system: true,
        });
    });
});
