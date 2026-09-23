import template from './sw-mixin-composable.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
        Shopware.Mixin.getByName('salutation'),
    ],

    props: {
        customer: {
            type: Object,
            required: true,
        },
    },

    methods: {
        onGreet() {
            this.createNotificationSuccess({ message: 'greeted' });
        },
    },
};
