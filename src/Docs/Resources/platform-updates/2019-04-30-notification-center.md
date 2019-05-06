[titleEn]: <>(Notification center)

The administration now has a notification center in every `sw-page`.
This is not a breaking change, you can still make notifications the same way as before.
But there are some nice new Features that you may want to know:

### The notification mixin
Currently there is a `notification.mixin.js` which only abstracts the store and sets a notification `variant`, depending on the method you call.
It is recommended to use the store directly instead to create or update notifications (because of the update functionality).

### Store functionality
You can create notficiations the following way:
```
this.$store.dispatch('notification/createNotification', {
    title: 'My title',
    message: 'My message',
    variant: 'info'
}).then((notificationId) => {
    // Save the id to modify the notification in the future.
});
```
And you can update your notification the following way:
```
this.$store.dispatch('notification/updateNotification', {
    uuid: mySavedNotificationId
    message: 'changed message'
}).then((notificationId) => {
    // The notification id stays the same here
});
```
The update action is very flexible. You can for example set `growl: true` to show the user a growl message (again).
You can also set `visited: false` to mark the notification as not seen by the user. 
There is also a way to set the `visited` parameter dynamically depending on data changes (have a look at the `metadata` parameter).
If the user deletes the notification and you update it, it will be recreated with the default values and your specified values.

### Possible notification parameters
* `title` **required** -> The title of the notification.
* `message` **required** -> The text of the notification.
* `variant` **recommended** -> The styling of the notification. Possible values are `success`, `info`, `warning` and `error`. If set to `success` the notification will be growl only. The default value is `info`.
* `system` **optional** -> Applies also to the styling of the notification. If set to true it will be darker. The default is `false`.
* `autoClose` **optional** -> If set to true the growl notification will close after the specified `duration`. The default is `true`.
* `duration` **optional** -> The duration of the growl message in ms. The default is `5000`

New parameters
* `growl` **recommended** -> Show the notification as a growl message. It will also be in the notification center. The default is `true`, but you should consider setting this to `false` to not overwhelm the user in notifications.
* `visited` **optional** -> If set to false, the notification is mark as not seen by the user and will be displayed so. The default is `false`.
* `isLoading` **optional** -> Shows a loading indicator if set to true. Also the notification will not be saved if it is set to `true`. If The default is `false`
* `metadata` **optional** -> You can store a object here. If the object is different from the already attached one, the notification will automatically set `visited` to false (as long as not other specified). This is useful to show a progress in the notification where you want to notify the user about progress changes.