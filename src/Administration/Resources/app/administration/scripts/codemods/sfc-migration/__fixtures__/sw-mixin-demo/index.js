import template from './sw-mixin-demo.html.twig';

export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    methods: {
        finish() {
            this.createNotificationSuccess({ message: 'done' });
        },
    },
};
