import { Mixin } from 'src/core/shopware';
import './sw-field-copyable.scss';
import template from './sw-field-copyable.html.twig';

/**
 * @private
 */
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
        },
        tooltip: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    data() {
        return {
            wasCopied: false
        };
    },

    computed: {
        tooltipText() {
            const textName = this.$parent.label;

            if (this.wasCopied) {
                return `${textName} ${this.$tc('global.sw-field-copyable.tooltip.wasCopied')}`;
            }

            return `${textName} ${this.$tc('global.sw-field-copyable.tooltip.canCopy')}`;
        },

        id() {
            return `sw-field--${this.$vnode.tag}`;
        }
    },

    methods: {
        copyToClipboard() {
            const el = this.$parent.$refs.textfield;

            if (el.disabled) {
                this.disabled = true;
            }

            if (this.disabled) {
                el.removeCustomField('disabled');
            }

            el.select();

            try {
                document.execCommand('copy');

                if (this.tooltip) {
                    this.tooltipSuccess();
                } else {
                    this.notificationSuccess();
                }
            } catch (err) {
                this.createNotificationError({
                    title: this.$tc('global.sw-field.notification.notificationCopyFailureTitle'),
                    message: this.$tc('global.sw-field.notification.notificationCopyFailureMessage')
                });
            }

            window.getSelection().removeAllRanges();
            if (this.disabled) {
                el.setCustomField('disabled', 'disabled');
            }
        },

        tooltipSuccess() {
            this.wasCopied = true;
        },

        notificationSuccess() {
            this.createNotificationInfo({
                title: this.$tc('global.sw-field.notification.notificationCopySuccessTitle'),
                message: this.$tc('global.sw-field.notification.notificationCopySuccessMessage')
            });
        },

        resetTooltipText() {
            this.wasCopied = false;
        }
    }
};
