import template from './sw-condition-tree-node.html.twig';
import './sw-condition-tree-node.scss';

const { Component } = Shopware;

/**
 * @private
 * @package business-ops
 */
Component.register('sw-condition-tree-node', {
    template,

    inject: [
        'conditionDataProviderService',
        'createCondition',
        'insertNodeIntoTree',
        'removeNodeFromTree',
    ],

    props: {
        level: {
            type: Number,
            required: true,
        },

        condition: {
            type: Object,
            required: true,
        },

        parentCondition: {
            type: Object,
            required: false,
            default: null,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },

        insertBefore: {
            type: Function,
            required: false,
            default: null,
        },

        insertAfter: {
            type: Function,
            required: false,
            default: null,
        },
    },

    computed: {
        conditionNodeComponent() {
            return this.conditionDataProviderService.getComponentByCondition(this.condition);
        },
    },

    methods: {
        deleteNode() {
            this.removeNodeFromTree(this.parentCondition, this.condition);
        },

        insertNewNodeBefore() {
            if (typeof this.insertBefore === 'function') {
                this.insertBefore();
                return;
            }

            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.parentCondition.id,
                    this.condition.position,
                    [],
                ),
            );
        },

        insertNewNodeAfter() {
            if (typeof this.insertAfter === 'function') {
                this.insertAfter();
                return;
            }

            this.insertNodeIntoTree(
                this.parentCondition,
                this.createCondition(
                    this.conditionDataProviderService.getPlaceholderData(),
                    this.parentCondition.id,
                    this.condition.position + 1,
                    [],
                ),
            );
        },
    },
});
