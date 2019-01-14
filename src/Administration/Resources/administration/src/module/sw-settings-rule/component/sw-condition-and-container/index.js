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
        },
        highestSort() {
            const children = this.condition.children;
            if (!children || !children.length) {
                return 0;
            }

            return children[children.length - 1].sort;
        },
        sortedChildren() {
            if (!this.condition.children) {
                return [];
            }
            return this.condition.children.sort((child1, child2) => { return child1.sort - child2.sort; });
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
                this.createCondition('placeholder', this.highestSort + 1);
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
        onAddAndClick() {
            this.createCondition('placeholder', this.highestSort + 1);
        },
        onAddChildClick() {
            this.createCondition('Shopware\\Core\\Framework\\Rule\\Container\\OrRule', this.highestSort + 1);
        },
        createCondition(type, sort) {
            const condition = Object.assign(
                this.conditionAssociations.create(),
                {
                    type: type,
                    parentId: this.condition.id,
                    sort: sort
                }
            );
            this.condition.children.push(condition);
        },
        createPlaceholderBefore(element) {
            const newSort = element.sort;
            this.condition.children.forEach(child => {
                if (child.sort < newSort) {
                    return;
                }

                child.sort += 1;
            });

            this.createCondition('placeholder', newSort);
        },
        createPlaceholderAfter(element) {
            const newSort = element.sort;
            this.condition.children.forEach(child => {
                if (child.sort <= newSort) {
                    return;
                }

                child.sort += 1;
            });

            this.createCondition('placeholder', newSort + 1);
        },
        onDeleteAll() {
            if (this.level === 0) {
                this.condition.children.forEach((child) => {
                    child.delete();
                    this.deleteChildren(child.children);
                });
            } else {
                this.deleteChildren(this.condition.children);
            }

            this.condition.children = [];

            this.$emit('delete-condition', this.condition);
        },
        deleteChildren(children) {
            children.forEach((child) => {
                if (child.children.length > 0) {
                    this.deleteChildren(child.children);
                }
                child.remove();
            });
        },
        onDeleteCondition(condition) {
            const oldSort = condition.sort;
            this.condition.children.forEach(child => {
                if (child.sort < oldSort) {
                    return;
                }

                child.sort -= 1;
            });

            condition.delete();
            this.condition.children.splice(this.condition.children.indexOf(condition), 1);

            if (this.condition.children.length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createPlaceholder();
                    }
                });
            }
        }
    }
});
