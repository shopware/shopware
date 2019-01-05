import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.less';

/**
 * @public
 * @description TODO: Add description
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-and-container :condition="condition"></sw-condition-and-container>
 */
Component.register('sw-condition-and-container', {
    template,

    inject: ['ruleConditionService'],
    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    /**
     * All additional passed attributes are bound explicit to the correct child element.
     */
    inheritAttrs: false,

    props: {
        condition: {
            type: Object,
            required: false,
            default() {
                return {};
            }
        },
        conditionAssociations: {
            type: Object,
            required: true
        },
        level: {
            type: Number,
            required: true,
            default() {
                return 1;
            }
        }
    },

    computed: {
        containerRowClass() {
            return this.level % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';
        }
    },

    mounted() {
        this.$on('finish-loading', this.onFinishLoading);
        this.createComponent();
    },

    methods: {
        createComponent() {
            this.condition.value = {};

            if (typeof this.condition.children === 'undefined') {
                this.condition.children = [];
                return;
            }

            if (!this.condition.children.length) {
                this.createPlaceholder(this.condition.id);
            }
        },
        onFinishLoading() {
            this.createComponent();
        },
        getComponent(type) {
            const condition = this.ruleConditionService.getByType(type);
            if (!condition) {
                return 'sw-condition-not-found';
            }

            return condition.component;
        },
        onAddAndClick(parentId) {
            this.createPlaceholder(parentId);
        },
        createPlaceholder(parentId) {
            const child = Object.assign(
                this.conditionAssociations.create(),
                {
                    type: 'placeholder',
                    parentId: parentId
                }
            );
            this.condition.children.push(child);
        },
        onAddChildClick(parentId) {
            const condition = Object.assign(
                this.conditionAssociations.create(),
                {
                    type: 'Shopware\\Core\\Framework\\Rule\\Container\\OrRule',
                    parentId: parentId
                }
            );
            this.condition.children.push(condition);
        },
        onDeleteAll() {
            for (let i = this.condition.children.length; i > 0; i -= 1) {
                this.condition.children.pop().delete();
            }
            this.$emit('delete-condition', this.condition);
        },
        onDeleteCondition(condition) {
            condition.delete();
            this.condition.children.splice(this.condition.children.indexOf(condition), 1);
        }
    }
});
