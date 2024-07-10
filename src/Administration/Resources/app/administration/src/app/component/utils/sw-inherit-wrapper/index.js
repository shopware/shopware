import './sw-inherit-wrapper.scss';
import template from './sw-inherit-wrapper.html.twig';

const { Component } = Shopware;

/**
 * @package admin
 *
 * @private
 * @description Wrapper for inherited data with toggle
 * @status ready
 * @example-type dynamic
 * @component-example
 * <sw-inherit-wrapper
 *     v-model="entity.value"
 *     :inheritedValue="inheritedEntity.value"
 *     :customInheritationCheckFunction="function(value) => {return true;}"
 *     :customRestoreFunction="function(value) => {return null;}"
 *     :customRemoveInheritanceFunction="function(value) => {return null;}"
 *     :disabled="false"
 *     label="Your label"
 *     :isAssociation="false"
 *     :hasParent="false"
 *     :required="true">
 *
 *     <template #content="{
 *          currentValue,
 *          updateCurrentValue,
 *          isInherited,
 *          isInheritField,
 *          toggleInheritance,
 *          restoreInheritance,
 *          removeInheritance
 *      }"><!-- Your Content --></template>
 *
 * </sw-inherit-wrapper>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-inherit-wrapper', {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['feature'],

    props: {
        // eslint-disable-next-line vue/require-prop-types
        value: {
            required: true,
        },

        // eslint-disable-next-line vue/require-prop-types
        inheritedValue: {
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        label: {
            type: String,
            required: false,
            default: null,
        },

        required: {
            type: Boolean,
            required: false,
            default: false,
        },

        isAssociation: {
            type: Boolean,
            required: false,
            default: false,
        },

        hasParent: {
            type: Boolean,
            required: false,
            // eslint-disable-next-line vue/no-boolean-default
            default: undefined,
        },

        // custom inheritation check which returns true or false
        customInheritationCheckFunction: {
            type: Function,
            required: false,
            default: null,
        },

        // custom reset inheritance function
        customRestoreInheritanceFunction: {
            type: Function,
            required: false,
            default: null,
        },

        // custom remove inheritance function
        customRemoveInheritanceFunction: {
            type: Function,
            required: false,
            default: null,
        },

        helpText: {
            type: String,
            required: false,
            default: null,
        },

        error: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            forceInheritanceRemove: false,
        };
    },

    computed: {
        currentValue: {
            get() {
                return this.isInherited ? this.inheritedValue : this.value;
            },

            set(newValue) {
                if (this.isInherited && newValue === this.inheritedValue) {
                    return;
                }

                if (!this.isInherited && newValue !== this.inheritedValue) {
                    this.updateValue(newValue, 'restore');
                    return;
                }

                this.removeInheritance(newValue);
            },
        },

        isInheritField() {
            // manual check if parent exists
            if (this.hasParent !== undefined) {
                return this.hasParent;
            }

            // automatic check if parent for inheritation exists
            return !(this.inheritedValue === null || typeof this.inheritedValue === 'undefined');
        },

        isInherited() {
            // if parent does not exist or has data or inheritance removing was forced
            if (!this.isInheritField || this.forceInheritanceRemove) {
                return false;
            }

            // if customInheritationCheckFunction exists
            if (typeof this.customInheritationCheckFunction === 'function') {
                return this.customInheritationCheckFunction(this.value);
            }

            // if association
            if (this.isAssociation && this.value) {
                return this.value.length <= 0;
            }

            return this.value === null || this.value === undefined;
        },

        labelClasses() {
            return {
                'has--error': !!this.error,
            };
        },
    },

    methods: {
        updateCurrentValue(value) {
            this.currentValue = value;
        },

        updateValue(value, inheritanceEventName) {
            this.$emit('update:value', value);
            this.$emit(`inheritance-${inheritanceEventName}`);
        },

        toggleInheritance() {
            if (this.isInherited) {
                this.removeInheritance();
            } else {
                this.restoreInheritance();
            }
        },

        restoreInheritance() {
            this.forceInheritanceRemove = false;

            // if customRestoreInheritanceFunction exists
            if (typeof this.customRestoreInheritanceFunction === 'function') {
                this.updateValue(this.customRestoreInheritanceFunction(this.value), 'restore');
                return;
            }

            // if association
            if (this.isAssociation) {
                // remove all items from value
                this.value.getIds().forEach((id) => {
                    this.value.remove(id);
                });

                // return new value
                this.updateValue(this.value, 'restore');
                return;
            }

            this.$emit('update:value', null);
        },

        removeInheritance(newValue = this.currentValue) {
            // if customRemoveInheritanceFunction exists
            if (typeof this.customRemoveInheritanceFunction === 'function') {
                this.updateValue(this.customRemoveInheritanceFunction(newValue, this.value), 'remove');
                return;
            }

            // if association
            if (this.isAssociation && newValue && this.value) {
                // remove all items
                this.restoreInheritance();

                if (newValue.length <= 0) {
                    this.forceInheritanceRemove = true;
                }

                // add each item from the parentValue to the original value
                newValue.forEach((item) => {
                    this.value.add(item);
                });

                this.updateValue(this.value, 'remove');
                return;
            }

            if (!newValue) {
                this.forceInheritanceRemove = true;
            }

            this.$emit('update:value', newValue);
        },
    },
});
