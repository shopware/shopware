/**
 * @sw-package framework
 */
import initializeNotifications from 'src/app/init/notification.init';
import { notification } from '@shopware-ag/meteor-admin-sdk';

describe('src/app/init/notification.init.ts', () => {
    beforeAll(() => {
        initializeNotifications();
    });

    beforeEach(() => {
        Shopware.Store.get('notification').growlNotifications = {};
    });

    it('should handle notificationDispatch requests', async () => {
        await notification.dispatch({
            title: 'Your title',
            message: 'Your message',
            variant: 'success',
            appearance: 'notification',
            growl: true,
            actions: [
                {
                    label: 'No',
                    method: () => {},
                },
                {
                    label: 'Cancel',
                    route: 'https://www.shopware.com',
                    disabled: false,
                },
            ],
        });

        const growlNotificationKey = Object.keys(Shopware.Store.get('notification').growlNotifications)[0];
        expect(Shopware.Store.get('notification').growlNotifications).toEqual({
            [growlNotificationKey]: expect.objectContaining({
                title: 'Your title',
                message: 'Your message',
                variant: 'positive',
            }),
        });
    });

    it('should handle notificationDispatch requests with fallback', async () => {
        await notification.dispatch({});

        const growlNotificationKey = Object.keys(Shopware.Store.get('notification').growlNotifications)[0];
        expect(Shopware.Store.get('notification').growlNotifications).toEqual({
            [growlNotificationKey]: expect.objectContaining({
                title: 'global.notification.noTitle',
                message: 'global.notification.noMessage',
                variant: 'info',
            }),
        });
    });

    const variantCases = [
        {
            given: 'success',
            expected: 'positive',
        },
        {
            given: 'positive',
            expected: 'positive',
        },
        {
            given: 'info',
            expected: 'info',
        },
        {
            given: 'neutral',
            expected: 'info',
        },
        {
            given: 'warning',
            expected: 'attention',
        },
        {
            given: 'attention',
            expected: 'attention',
        },
        {
            given: 'error',
            expected: 'critical',
        },
        {
            given: 'critical',
            expected: 'critical',
        },
    ];

    it.each(variantCases)('should handle notificationDispatch requests with variant %s', async ({ given, expected }) => {
        await notification.dispatch({
            title: 'Your title',
            message: 'Your message',
            variant: given,
            appearance: 'notification',
            growl: true,
            actions: [
                {
                    label: 'No',
                    method: () => {},
                },
                {
                    label: 'Cancel',
                    route: 'https://www.shopware.com',
                    disabled: false,
                },
            ],
        });

        const growlNotificationKey = Object.keys(Shopware.Store.get('notification').growlNotifications)[0];
        expect(Shopware.Store.get('notification').growlNotifications).toEqual({
            [growlNotificationKey]: expect.objectContaining({
                title: 'Your title',
                message: 'Your message',
                variant: expected,
            }),
        });
    });
});
