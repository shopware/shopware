[titleEn]: <>(Worker notification extensibility)

We have refactored the handling of worker / message queue responses and added a middleware
pattern to it. This enables third-party developers to react to messages in the queue easily.

### Implementation
```
import { WorkerNotification } from 'src/core/shopware';

WorkerNotification.register('newsletterRecipientTask', {
    name: 'Shopware\\Core\\Content\\Newsletter\\ScheduledTask\\NewsletterRecipientTask',
    fn: (next, data) => {
        console.log(data);

        // do your stuff and call next then
        next();
    }
});
```

#### What is available in the middleware function?

```
{
    $root,          // Vue root instance
    entry,          // Found entry
    name,           // Class name to filter the queue
    notification: { // Helper methods for notifications
        create,
        update
    },
    queue,          // Entire queue
    response        // Queue status HTTP response
}
```
### Full example

```
import { WorkerNotification } from 'src/core/shopware';

let notificationId = null;
WorkerNotification.register('generateThumbnailsMessage', {
    name: 'Shopware\\Core\\Content\\Media\\Message\\GenerateThumbnailsMessage',
    fn: function middleware(next, { entry, $root, notification }) {
        // Create notification config object
        const config = {
            title: $root.$t('global.notification-center.worker-listener.thumbnailGeneration.title'),
            message: $root.$tc(
                'global.notification-center.worker-listener.thumbnailGeneration.message',
                entry.size
            ),
            variant: 'info',
            metadata: {
                size: entry.size
            },
            growl: false,
            isLoading: true
        };

        // Create new notification
        if (entry.size && notificationId === null) {
            notification.create(config).then((uuid) => {
                notificationId = uuid;
            });
            next();
        }

        // Update existing notification
        if (notificationId !== null) {
            config.uuid = notificationId;

            if (entry.size === 0) {
                config.title = $root.$t(
                    'global.notification-center.worker-listener.thumbnailGeneration.titleSuccess'
                );
                config.message = $root.$t(
                    'global.notification-center.worker-listener.thumbnailGeneration.messageSuccess'
                );
                config.isLoading = false;
            }
            notification.update(config);
        }

        // do your stuff and call next then
        next();
    }
});
```

#### Best practice
It's best practice to create an initializer decorator to add new worker notification middleware
function.

```
import { Application } from 'src/core/shopware';

Application.addInitializerDecorator('worker', (service) => {
    const factory = Application.getContainer('factory').workerNotification;
    
    factory.register('newsletterRecipientTask', {
        name: 'Shopware\\Core\\Content\\Newsletter\\ScheduledTask\\NewsletterRecipientTask',
        fn: (next, data) => {
            console.log(data);
    
            // do your stuff and call next then
            next();
        }
    });
    
    return service;
});
```