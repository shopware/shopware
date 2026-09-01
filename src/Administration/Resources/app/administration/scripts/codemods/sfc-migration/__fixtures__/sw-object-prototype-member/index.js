import template from './sw-object-prototype-member.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    data() {
        return {
            mapping: {},
        };
    },

    computed: {
        // `hasOwnProperty` and `constructor` resolve on Object.prototype, so a table lookup keyed by
        // this name finds an inherited value unless the table has a null prototype.
        mapped() {
            return Object.prototype.hasOwnProperty.call(this.mapping, 'key');
        },
    },

    methods: {
        readOwn(key) {
            return this.hasOwnProperty(key) ? this.mapping[key] : null;
        },
    },
};
