import template from './sw-condition-tree-node.html.twig';

const { Component } = Shopware;

Component.register('sw-condition-tree-node', {
    template,

    inject: [
        'conditionDataProviderService',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree'
    ],

    props: {
        level: {
            type: Number,
            required: true
        },

        condition: {
            type: Object,
            required: true
        },

        parentCondition: {
            type: Object,
            required: false,
            default: null
        }
    },

    computed: {
        conditionNodeComponent() {
            return this.conditionDataProviderService.getComponentByCondition(this.condition);
        }
    },

    methods: {
        deleteNode() {
            this.removeNodeFromTree(this.parentCondition, this.condition);
        },

        insertNewNodeBefore() {
            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.parentCondition.id,
                    this.condition.position,
                    []
                )
            );
        },

        insertNewNodeAfter() {
            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.parentCondition.id,
                    this.condition.position + 1,
                    []
                )
            );
        }
    }
});
