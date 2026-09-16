/**
 * @sw-package framework
 */
import useNotification from './use-notification';
import type { NotificationType } from '../store/notification.store';

type NotificationApi = ReturnType<typeof useNotification>;
type VoidNotificationMethod = Exclude<keyof NotificationApi, 'createNotification'>;

describe('src/app/composables/use-notification', () => {
    let createNotification: jest.Mock;

    beforeEach(() => {
        createNotification = jest.fn().mockReturnValue('notification-id');
        window.Shopware = {
            Store: {
                get: jest.fn().mockReturnValue({ createNotification }),
            },
        } as unknown as typeof Shopware;
    });

    it('createNotification delegates to the notification store and returns its id', () => {
        const { createNotification: create } = useNotification();
        const notification: NotificationType = { message: 'hello' };

        expect(create(notification)).toBe('notification-id');
        expect(createNotification).toHaveBeenCalledWith(notification);
    });

    it.each<[VoidNotificationMethod, { variant: string; title: string }]>([
        [
            'createNotificationSuccess',
            { variant: 'success', title: 'global.default.success' },
        ],
        [
            'createNotificationInfo',
            { variant: 'info', title: 'global.default.info' },
        ],
        [
            'createNotificationWarning',
            { variant: 'warning', title: 'global.default.warning' },
        ],
        [
            'createNotificationError',
            { variant: 'error', title: 'global.default.error' },
        ],
    ])('%s applies its default variant and title', (method, defaults) => {
        const composable = useNotification();

        composable[method]({ message: 'body' });

        expect(createNotification).toHaveBeenCalledWith({ ...defaults, message: 'body' });
    });

    it.each<[VoidNotificationMethod, string]>([
        [
            'createSystemNotificationSuccess',
            'success',
        ],
        [
            'createSystemNotificationInfo',
            'info',
        ],
        [
            'createSystemNotificationWarning',
            'warning',
        ],
        [
            'createSystemNotificationError',
            'error',
        ],
    ])('%s sets system: true with its variant', (method, variant) => {
        const composable = useNotification();

        composable[method]({ message: 'body' });

        expect(createNotification).toHaveBeenCalledWith({ variant, system: true, message: 'body' });
    });

    it('createSystemNotification marks the notification as system without forcing a variant', () => {
        const { createSystemNotification } = useNotification();

        createSystemNotification({ message: 'body' });

        expect(createNotification).toHaveBeenCalledWith({ system: true, message: 'body' });
    });

    it('lets an explicit config override the applied defaults', () => {
        const { createNotificationSuccess } = useNotification();

        createNotificationSuccess({ title: 'custom.title', message: 'body' });

        expect(createNotification).toHaveBeenCalledWith({
            variant: 'success',
            title: 'custom.title',
            message: 'body',
        });
    });
});
