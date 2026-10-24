/*
 * @sw-package inventory
 */

import template from './sw-fixture-detail.html.twig';

const { Mixin } = Shopware;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

const errorConfiguration = {
    productName: { product: ['name'] },
};

export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    emits: ['saved'],

    mixins: [Mixin.getByName('notification'), 'placeholder'],

    props: {
        productId: {
            type: String,
            required: true,
        },
        value: {
            type: [String, Number],
            required: false,
            default: null,
        },
        /**
         * @deprecated tag:v6.8.0 - Use `productId` instead
         */
        legacyId: {
            type: String,
            default: null,
        },
        mode: {
            type: String,
            default: 'edit',
            deprecated: { version: 'v6.9.0.0', comment: 'Use "variant" instead.' },
        },
        items: {
            type: Array,
            default() {
                return [];
            },
        },
    },

    data() {
        return {
            isLoading: false,
            title: '',
            counter: 0,
            selection: null,
        };
    },

    computed: {
        ...mapPageErrors(errorConfiguration),

        heading() {
            return 'Fixture detail';
        },

        searchTerm: {
            get() {
                return this.title;
            },
            set(value) {
                this.title = value;
            },
        },
    },

    methods: {
        async onSave(product, ...rest) {
            this.$emit('save-start', product);
            await Promise.resolve(rest);
            this.$emit('saved');

            return true;
        },

        /**
         * @private
         */
        resetForm(force = false) {
            this.isLoading = force;
        },

        _cleanUp() {
            this.selection = null;
        },

        /**
         * @param {string} name
         * @returns {number}
         */
        countLetters(name) {
            return name.length;
        },
    },
};
