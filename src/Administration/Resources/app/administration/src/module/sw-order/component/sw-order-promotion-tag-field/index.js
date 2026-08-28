import './sw-order-promotion-tag-field.scss';
import template from './sw-order-promotion-tag-field.html.twig';

/**
 * @sw-package checkout
 */

const { Utils } = Shopware;
const { format } = Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: [
        'update:value',
        'on-remove-code',
    ],

    props: {
        currency: {
            type: Object,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        taggedFieldListClasses() {
            return {
                'sw-tagged-field__tag-list--disabled': this.disabled,
            };
        },

        // Unlike the base field, also hide a not yet submitted code on blur,
        // so it is clear that it was not applied to the order.
        taggedFieldInputClasses() {
            return {
                'sw-tagged-field__input--full-width': !this.hasValues,
                'sw-tagged-field__input--hidden': !this.hasFocus && (this.hasValues || !!this.newTagName),
            };
        },
    },

    methods: {
        performAddTag(event) {
            if (this.disabled || this.noTriggerKey(event)) {
                return;
            }

            if (typeof this.newTagName !== 'string' || this.newTagName === '') {
                return;
            }

            const tag = this.value.find((item) => item.code === this.newTagName);

            if (tag) {
                return;
            }

            const newTagItem = {
                code: this.newTagName,
            };

            this.$emit('update:value', [
                ...this.value,
                newTagItem,
            ]);

            this.newTagName = '';
        },

        dismissTag(item) {
            this.$emit('on-remove-code', item);
        },

        setFocus(hasFocus) {
            if (this.disabled) {
                return;
            }

            this.hasFocus = hasFocus;
            if (hasFocus) {
                this.$refs.taggedFieldInput.focus();
            }
        },

        getPromotionCodeDescription(item) {
            if (!item.discountId) return item.code;

            const { value, discountScope, discountType, groupId } = item;

            const discountValue =
                discountType === 'percentage' ? value : format.currency(Number(value), this.currency.isoCode);

            return this.$t(`sw-order.createBase.textPromotionDescription.${discountScope}.${discountType}`, {
                value: discountValue,
                groupId,
            });
        },
    },
};
