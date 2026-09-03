import template from './sw-mixin-callback-not-method.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('rule-between-operator'),
    ],

    props: {
        condition: {
            type: Object,
            required: true,
        },
    },

    computed: {
        // The mixin called this; a computed of the same name only ever produced a value.
        ensureValueExist() {
            return Boolean(this.condition.value);
        },
    },
};
