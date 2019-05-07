import utils from 'src/core/service/util.service';
import { Mixin } from 'src/core/shopware';
import template from './sw-checkbox-field.html.twig';
import './sw-checkbox-field.scss';

/**
 * @public
 * @description Boolean input field based on checkbox.
 * @status ready
 * @example-type static
 * @component-example
 * <sw-checkbox-field label="Name" v-model="aBooleanProperty"></sw-checkbox-field>
 */
export default {
    name: 'sw-checkbox-field',
    template,
    inheritAttrs: false,

    model: {
        prop: 'value',
        event: 'change'
    },

    mixins: [
        Mixin.getByName('sw-form-field')
    ],

    props: {
        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        value: {
            type: Boolean,
            required: false,
            default: null
        },

        inheritedValue: {
            type: Boolean,
            required: false,
            default: null
        }
    },

    data() {
        return {
            currentValue: this.value,
            id: utils.createId()
        };
    },

    computed: {
        swCheckboxFieldClasses() {
            return {
                'has--error': this.hasError,
                'is--disabled': this.disabled,
                'is--inherited': this.isInherited
            };
        },

        identification() {
            return this.formFieldName() || `sw-field--${this.id}`;
        },

        hasError() {
            return this.actualError && this.actualError.code !== 0;
        },

        inputState() {
            if (this.isInherited) {
                return this.inheritedValue;
            }

            return this.currentValue || false;
        }
    },

    watch: {
        value() { this.currentValue = this.value; }
    },

    methods: {
        onChange(changeEvent) {
            this.$emit('change', changeEvent.target.checked);
        },

        restoreInheritance() {
            this.$emit('change', null);
        }
    }
};
