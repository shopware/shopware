import './sw-order-promotion-tag-field.scss';
import template from './sw-order-promotion-tag-field.html.twig';

const { Component, Utils } = Shopware;
const { format } = Utils;

Component.register('sw-order-promotion-tag-field', {
    template,

    extends: 'sw-tagged-field',

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
    },

    methods: {
        performAddTag(event) {
            if (this.disabled || this.noTriggerKey(event)) {
                return;
            }

            if (typeof this.newTagName !== 'string' || this.newTagName === '') {
                return;
            }

            const tag = this.value.find(item => item.code === this.newTagName);

            if (tag) {
                return;
            }

            const newTagItem = {
                code: this.newTagName,
            };

            this.$emit('change', [...this.value, newTagItem]);
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

            const discountValue = discountType === 'percentage'
                ? value
                : format.currency(Number(value), this.currency.shortName);

            return this.$tc(
                `sw-order.createBase.textPromotionDescription.${discountScope}.${discountType}`, 0,
                { value: discountValue, groupId },
            );
        },
    },
});
