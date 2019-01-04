import { Component, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import template from './sw-tagged-field.html.twig';
import './sw-tagged-field.less';

/**
 * @public
 * @status ready
 * @example-type code-only
 * @component-example
 * <sw-tagged-field label="Label" :addOnKey="[13, ',']" v-model="array">
 * </sw-tagged-field>
 */
Component.register('sw-tagged-field', {
    template,

    mixins: [
        Mixin.getByName('validation')
    ],

    props: {
        placeholder: {
            type: String,
            required: false,
            default: ''
        },
        value: {
            type: Array,
            required: true
        },
        label: {
            type: String,
            default: ''
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },
        helpText: {
            type: String,
            required: false,
            default: ''
        },
        addOnKey: {
            type: Array,
            required: false,
            default: () => [13]
        }
    },

    data() {
        return {
            newTag: '',
            hasError: false,
            tags: []
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        taggedClasses() {
            return {
                'has--error': !this.isValid || this.hasError,
                'is--disabled': this.disabled
            };
        },
        id() {
            return `sw-tagged-field--${utils.createId()}`;
        }
    },

    methods: {
        createdComponent() {
            this.tags = this.value;
            this.addEventListeners();
        },

        dismissLastTag() {
            if (this.newTag.length > 0) {
                return;
            }

            if (!this.tags.length) {
                return;
            }

            this.dismissTag(this.tags.length - 1);
        },

        dismissTag(index) {
            this.tags.splice(index, 1);

            this.emitChanges();
        },

        emitChanges() {
            this.$emit('input', this.tags);
        },

        performAddTag(event) {
            if (this.disabled || (event && this.noTriggerKey(event))) {
                return;
            }

            if (!this.newTag) {
                return;
            }

            this.tags.push(this.newTag);
            this.newTag = '';
        },

        noTriggerKey(event) {
            const triggerKey = this.addOnKey.indexOf(event.keyCode) !== -1
                || this.addOnKey.indexOf(event.key) !== -1;

            if (triggerKey) {
                event.preventDefault();
            }
            return !triggerKey;
        }
    }
});
