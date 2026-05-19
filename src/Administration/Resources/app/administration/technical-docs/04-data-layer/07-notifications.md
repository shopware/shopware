# Notifications

Notifications are used to inform users of important events, such as errors, success messages or of a completed action. 

### Use notification mixin

To create notifications, you can use the `notification` mixin. The mixin provides various methods for creating notifications:
 * `createNotificationSuccess`
 * `createNotificationInfo`
 * `createNotificationWarning`
 * `createNotificationError`

```javascript
Shopware.Component.register('my-component', {
    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        success: function () {
            this.createNotificationSuccess({ title: 'The action was completed' })
        }
    }
});
```

You can also add actions to the notifications. Actions are buttons that allow users to take specific actions directly from the notification, such as navigation to a route or running a function:

```javascript
Shopware.Component.register('my-component', {
    mixins: [
        Mixin.getByName('notification')
    ],

    methods: {
        success: function () {
            this.createNotificationSuccess({ 
                title: 'The action was completed',
                actions: [
                    {
                        label: 'Go to products',
                        route: { name: 'sw.product.index' },
                    },
                    {
                        label: 'Do something else',
                        method: () => console.log('completed'),
                    }
                ],
            })
        }
    }
});
```

### Notification transformers

Notification transformers allow you to enhance backend notifications with actions and translations. This is particularly useful for creating interactive notifications that guide users to take specific actions.

You can register a transformer using the `registerTransformer` method on the `notification` store. Use the notification message value as the key for the transformer. The transformer function receives the notification object as its first argument and must return a new notification object.

The following shows how to register a transformer for the `notification.permissions.requested` notification:

```typescript
Shopware.Store.get('notification').registerTransformer(
    'notification.permissions.requested',
    (notification: NotificationType): NotificationType => {
        const root = Shopware.Application.getApplicationRoot() as App<Element>;

        return {
            ...notification,
            variant: 'warning' as NotificationVariant,
            title: root.$tc('sw-extension.notifications.reviewPermissionRequests.title'),
            message: root.$tc('sw-extension.notifications.reviewPermissionRequests.message'),
            actions: [
                {
                    label: root.$tc('sw-extension.notifications.reviewPermissionRequests.action'),
                    route: { name: 'sw.extension.my-extensions.listing' },
                },
            ],
        };
    },
);
```
