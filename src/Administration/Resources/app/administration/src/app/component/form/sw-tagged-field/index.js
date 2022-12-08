import template from './sw-tagged-field.html.twig';
import './sw-tagged-field.scss';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @deprecated tag:v6.6.0 - Will be private
 * @public
 * @status deprecated
 * @example-type code-only
 * @component-example
 * <sw-tagged-field label="Label" :addOnKey="['enter', ',']">
 * </sw-tagged-field>
 */
Component.register('sw-tagged-field', {
    template,

    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        value: {
            type: Array,
            required: false,
            default: () => [],
        },

        placeholder: {
            type: String,
            required: false,
            default() {
                return this.$tc('global.sw-tagged-field.text-default-placeholder');
            },
        },

        addOnKey: {
            type: Array,
            required: false,
            default: () => ['enter'],
        },
    },

    data() {
        return {
            newTagName: '',
            hasFocus: false,
        };
    },

    computed: {
        hasValues() {
            return this.value.length > 0;
        },

        taggedFieldClasses() {
            return {
                'has--focus': this.hasFocus,
            };
        },

        taggedFieldInputClasses() {
            return {
                'sw-tagged-field__input--full-width': !this.hasValues,
                'sw-tagged-field__input--hidden': this.hasValues && !this.hasFocus,
            };
        },
    },

    methods: {
        dismissLastTag() {
            if (typeof this.newTagName === 'string' && this.newTagName.length > 0) {
                return;
            }

            this.$emit('change', this.value.slice(0, this.value.length - 1));
        },

        dismissTag(index) {
            this.$emit('change', this.value.filter((item, itemIndex) => itemIndex !== index));
        },

        performAddTag(event) {
            if (this.disabled || this.noTriggerKey(event)) {
                return;
            }

            if (typeof this.newTagName !== 'string' || this.newTagName === '') {
                return;
            }

            this.$emit('change', [...this.value, this.newTagName]);
            this.newTagName = '';
        },

        setFocus(hasFocus) {
            this.hasFocus = hasFocus;
            if (hasFocus) {
                this.$refs.taggedFieldInput.focus();
            }
        },

        noTriggerKey(event) {
            const keyIndex = this.addOnKey.findIndex((eventKey) => {
                return eventKey.toLowerCase() === event.key.toLowerCase();
            });

            if (keyIndex === -1) {
                return true;
            }

            event.preventDefault();
            return false;
        },
    },
});
