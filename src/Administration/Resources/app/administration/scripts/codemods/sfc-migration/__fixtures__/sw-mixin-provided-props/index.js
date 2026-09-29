import template from './sw-mixin-provided-props.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('ruleContainer'),
    ],

    props: {
        entity: {
            type: Object,
            required: true,
        },
    },

    methods: {
        // The member the mixin watched for, reading two props the mixin declared itself.
        onAddPlaceholder() {
            this.createCondition({ type: null }, this.condition.id, this.nextPosition);
        },

        onRemove() {
            this.removeNodeFromTree(this.parentCondition, this.condition);
        },
    },
};
