import template from './notification-mixin-component.html.twig';

Shopware.Component.register('sw-notification-demo', {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    methods: {
        onSave() {
            this.createNotificationSuccess({ message: 'Saved' });
        },

        onFail() {
            this.createNotificationError({ message: 'Failed' });
        },
    },
});
