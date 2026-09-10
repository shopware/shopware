import template from './sw-mixin-internal-override.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    methods: {
        // Every create* helper of the mixin routes through this member, but the composable calls its
        // own copy, so the override would stop taking effect.
        createNotification(notification) {
            return Shopware.Store.get('notification').createNotification({ ...notification, system: true });
        },

        onSave() {
            this.createNotificationSuccess({ message: 'saved' });
        },
    },
};
