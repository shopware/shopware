import { Mixin } from 'src/core/shopware';
import template from './sw-field-copyable.html.twig';

export default {
    name: 'sw-field-copyable',
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        displayName: {
            type: String,
            required: true
        }
    },

    methods: {
        copyToClipboard() {
            const el = this.$parent.$refs.textfield;

            if (el.disabled) {
                this.disabled = true;
            }

            if (this.disabled) {
                el.removeAttribute('disabled');
            }

            el.select();

            try {
                document.execCommand('copy');
                this.createNotificationInfo({
                    title: this.$tc('global.sw-field.notification.notificationCopySuccessTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopySuccessMessage')
                });
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('global.sw-field.notification.notificationCopyFailureTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage')
                });
            }

            window.getSelection().removeAllRanges();
            if (this.disabled) {
                el.setAttribute('disabled', 'disabled');
            }
        }
    }
};
