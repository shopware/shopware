/* eslint-disable vue/one-component-per-file */
import template from './sw-simple-search-field.html.twig';
import templateFeatureNext16271 from './sw-simple-search-field.feature_next-16271.html.twig';
import './sw-simple-search-field.scss';

const { Component, Feature, Utils } = Shopware;

/**
 * @public
 * @description a search field with delayed update
 * @status ready
 * @example-type static
 * @component-example
 * <sw-simple-search-field
 *   v-model="value"
 *   :delay="1000"
 *   @input="onInput"
 *   @search-term-change="debouncedInputEvent"
 *  />
 */
if (Feature.isActive('FEATURE_NEXT_16271')) {
    Component.register('sw-simple-search-field', {
        template: templateFeatureNext16271,
        inheritAttrs: false,

        props: {
            variant: {
                type: String,
                required: false,
                default: 'default',
                validValues: ['default', 'inverted', 'form'],
                validator(value) {
                    if (!value.length) {
                        return true;
                    }
                    return ['default', 'inverted', 'form'].includes(value);
                },
            },

            value: {
                type: String,
                default: null,
                required: false,

            },

            delay: {
                type: Number,
                required: false,
                default: 400,
            },

            icon: {
                type: String,
                required: false,
                default: 'small-search',
            },
        },

        data() {
            return {
                onSearchTermChanged: Utils.debounce(function debounceInput(input) {
                    this.$emit('search-term-change', input);
                }, this.delay),
            };
        },

        computed: {
            fieldClasses() {
                return [
                    `sw-simple-search-field--${this.variant}`,
                ];
            },

            placeholder() {
                return this.$attrs.placeholder || this.$tc('global.sw-simple-search-field.defaultPlaceholder');
            },
        },

        methods: {
            onInput(input) {
                this.$emit('input', input);
                this.onSearchTermChanged(input);
            },
        },
    });
} else {
    Component.register('sw-simple-search-field', {
        template,
        inheritAttrs: false,

        /* @deprecated tag:v6.5.0 component will use @input and value
           instead of custom searchTerm and `search-term-change` */
        model: {
            prop: 'searchTerm',
            event: 'search-term-change',
        },

        props: {
            variant: {
                type: String,
                required: false,
                default: 'default',
                validValues: ['default', 'inverted', 'form'],
                validator(value) {
                    if (!value.length) {
                        return true;
                    }
                    return ['default', 'inverted', 'form'].includes(value);
                },
            },

            /* @deprecated tag:v6.5.0 use value and @input */
            searchTerm: {
                type: String,
                required: false,
                default: null,
            },

            delay: {
                type: Number,
                required: false,
                default: 400,
            },

            icon: {
                type: String,
                required: false,
                default: 'small-search',
            },
        },

        computed: {
            fieldClasses() {
                return [
                    `sw-simple-search-field--${this.variant}`,
                ];
            },

            placeholder() {
                return this.$attrs.placeholder || this.$tc('global.sw-simple-search-field.defaultPlaceholder');
            },

            /* @deprecated tag:v6.5.0
              `onSearchTermChanged` is relocated as `data()` value function instead computed property */
            onSearchTermChanged() {
                return Utils.debounce((input) => {
                    const validInput = input || '';
                    this.$emit('search-term-change', validInput.trim());
                }, this.delay);
            },
        },
    });
}
