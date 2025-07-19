import { createPinia, setActivePinia } from 'pinia';
import { POLL_BACKGROUND_INTERVAL } from '../../core/worker/worker-notification-listener';

describe('notifications.store', () => {
    beforeAll(() => {
        Shopware.Store.get('session').currentUser = { id: '1' } as EntitySchema.user;
    });

    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('should have the initial state', () => {
        const notification = Shopware.Store.get('notification');

        expect(notification.notifications).toEqual({});
        expect(notification.growlNotifications).toEqual({});
        expect(notification.threshold).toBe(5);
        expect(notification.workerProcessPollInterval).toEqual(POLL_BACKGROUND_INTERVAL);
    });

    it('slices the growl notifications when threshold is set', () => {
        const notification = Shopware.Store.get('notification');

        notification.growlNotifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };
        notification.setThreshold(2);

        expect(notification.threshold).toBe(2);
        expect(notification.growlNotifications).toEqual({
            a: { uuid: 'a' },
            b: { uuid: 'b' },
        });
    });

    it('clears the notifications for current user', () => {
        const notification = Shopware.Store.get('notification');
        const localStorageSpy = jest.spyOn(Storage.prototype, 'removeItem');

        notification.notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.clearNotificationsForCurrentUser();

        expect(notification.notifications).toEqual({});
        expect(localStorageSpy).toHaveBeenCalledWith('notifications#1');
    });

    it('clears the growl notifications for current user', () => {
        const notification = Shopware.Store.get('notification');

        notification.growlNotifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.clearGrowlNotificationsForCurrentUser();

        expect(notification.growlNotifications).toEqual({});
    });

    it('sets notifications', () => {
        const notification = Shopware.Store.get('notification');
        const notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.setNotifications(notifications);

        expect(notification.notifications).toEqual(notifications);
    });

    it('upserts a notification', () => {
        const notification = Shopware.Store.get('notification');
        notification.notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.upsertNotification({ uuid: 'a', title: 'updated' });
        notification.upsertNotification({ uuid: 'd', title: 'new' });

        expect(notification.notifications.a).toEqual({ uuid: 'a', title: 'updated' });
        expect(notification.notifications.d).toEqual({ uuid: 'd', title: 'new' });
    });

    it('removes a notification', () => {
        const notification = Shopware.Store.get('notification');
        const notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.notifications = notifications;
        notification.removeNotification(notifications.a);

        expect(notification.notifications).toEqual({
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        });
    });

    it('sets all notifications as visited', () => {
        const notification = Shopware.Store.get('notification');
        notification.notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.setAllNotificationsVisited();

        expect(notification.notifications).toEqual({
            a: { uuid: 'a', visited: true },
            b: { uuid: 'b', visited: true },
            c: { uuid: 'c', visited: true },
        });
    });

    it('upsert a growl notification', () => {
        const notification = Shopware.Store.get('notification');
        notification.growlNotifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };
        notification.upsertGrowlNotification({ uuid: 'a', title: 'updated' });
        notification.upsertGrowlNotification({ uuid: 'd', title: 'new' });

        expect(notification.growlNotifications.a).toEqual({ uuid: 'a', title: 'updated' });
        expect(notification.growlNotifications.d).toEqual({ uuid: 'd', title: 'new' });
    });

    it('removes a growl notification on upsert if length is bigger than threshold', () => {
        const notification = Shopware.Store.get('notification');
        notification.setThreshold(3);
        notification.growlNotifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };
        notification.upsertGrowlNotification({ uuid: 'd', title: 'new' });

        expect(notification.growlNotifications).toEqual({
            b: { uuid: 'b' },
            c: { uuid: 'c' },
            d: { uuid: 'd', title: 'new' },
        });
    });

    it('removes a growl notification', () => {
        const notification = Shopware.Store.get('notification');
        const growlNotifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.growlNotifications = growlNotifications;
        notification.removeGrowlNotification(growlNotifications.a);

        expect(notification.growlNotifications).toEqual({
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        });
    });

    it('creates a notification', () => {
        const notification = Shopware.Store.get('notification');
        const newId = notification.createNotification({ title: 'test', message: 'test' }) ?? '';

        expect(notification.notifications[newId]).toEqual(
            expect.objectContaining({
                uuid: newId,
                title: 'test',
                message: 'test',
            }),
        );
    });

    it('updates a notification if the new notification has the same id', () => {
        const notification = Shopware.Store.get('notification');
        const newId = notification.createNotification({ title: 'test', message: 'test' }) ?? '';

        notification.createNotification({ uuid: newId, title: 'updated', message: 'updated' });

        expect(notification.notifications[newId]).toEqual(
            expect.objectContaining({
                uuid: newId,
                title: 'updated',
                message: 'updated',
            }),
        );
    });

    it('creates a growl notification when new notification has no growl or it is true', () => {
        const notification = Shopware.Store.get('notification');
        notification.createNotification({ title: 'test', message: 'test' });

        const growlNotification = Object.values(notification.growlNotifications)[0];
        expect(growlNotification).toEqual(
            expect.objectContaining({
                title: 'test',
                message: 'test',
            }),
        );

        notification.createNotification({ title: 'test2', message: 'test2', growl: true });
        const growlNotification2 = Object.values(notification.growlNotifications)[1];
        expect(growlNotification2).toEqual(
            expect.objectContaining({
                title: 'test2',
                message: 'test2',
            }),
        );
    });

    it('creates a growl notification', () => {
        const notification = Shopware.Store.get('notification');
        notification.createGrowlNotification({ title: 'test', message: 'test' });
        const growlNotification = Object.values(notification.growlNotifications)[0];

        expect(growlNotification).toEqual(
            expect.objectContaining({
                title: 'test',
                message: 'test',
            }),
        );
    });

    it('removes a growl notification after the duration', () => {
        jest.useFakeTimers();
        const notification = Shopware.Store.get('notification');
        notification.createGrowlNotification({ title: 'test', message: 'test', duration: 1000 });
        const growlNotification = Object.values(notification.growlNotifications)[0];

        expect(notification.growlNotifications).toEqual({
            [growlNotification.uuid ?? '']: growlNotification,
        });

        jest.advanceTimersByTime(1000);

        expect(notification.growlNotifications).toEqual({});
    });

    it('updates a growl notification and creates a growl notification', () => {
        const notification = Shopware.Store.get('notification');
        notification.notifications = {
            a: { uuid: 'a' },
            b: { uuid: 'b' },
            c: { uuid: 'c' },
        };

        notification.updateNotification({ uuid: 'a', title: 'updated', growl: true });

        expect(notification.notifications.a).toEqual(expect.objectContaining({ uuid: 'a', title: 'updated' }));
        expect(Object.values(notification.growlNotifications)[0]).toEqual(expect.objectContaining({ title: 'updated' }));
    });
});
