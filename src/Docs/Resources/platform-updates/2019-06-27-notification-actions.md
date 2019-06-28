[titleEn]: <>(Action support for notifications and alerts)

The notifications can now receive an additional `actions` array.
This array contains very small objects which can be used to define a label and what should happen when clicking the action button.

Single action example:
```
{
    label: 'Button label',
    route: { name: 'sw.settings.index' }
}
```
- `$router.push()` will get called when the action button was clicked.
- By default the notification will be closed when the route change is run.
- When no route is given the notification will close on click by default.
- When actions are in use, a button bar with the defined actions will appear automatically inside the notification.

Full example for notification actions:
```
this.$store.dispatch('notification/createNotification', {
    title: 'Shopware update',
    message: 'Shopware 6.0-ea2 is now available. Do you want to update now?',
    variant: 'info',
    system: true,
    actions: [{
        label: 'Cancel'
    }, {
        label: 'Update now',
        route: { name: 'sw.settings.index' }
    }],
    autoClose: false
});
```
## Manual mode for sw-alert

Notifications are essentially only `sw-alert` components which are composed together.

You can also use the actions on the alert directly by putting `sw-button` components inside the new `actions` slot. This also happens in the notifications base file as well.

Manual example:

```
<sw-alert variant="error" title="Error">
    An error occurred when trying to save the entity.
    <template #actions>
        <sw-button>Ignore</sw-button>
        <sw-button>View error log</sw-button>
        <sw-button>Try again</sw-button>
    </template>
</sw-alert>
```
- All color variants and appearances of `sw-alert` are supported.
- The buttons will get different styling automatically. Please do not use further variants/props like `primary` or `size`. This may break the appearance.
