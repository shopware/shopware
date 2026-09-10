import template from './sw-mixin-callback-method.html.twig';

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

    methods: {
        // The method the mixin called on its host before writing the pair back.
        ensureValueExist() {
            if (!this.condition.value) {
                this.condition.value = {};
            }
        },
    },
};
