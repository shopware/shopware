import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.less';

const PLACEHOLDER_NAME = 'placeholder';
const OR_CONTAINER_NAME = 'swOrContainer';

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
        nextPosition() {
            const children = this.condition.children;
            if (!children || !children.length) {
                return 1;
            }

            return children[children.length - 1].position + 1;
        },
        sortedChildren() {
            if (!this.condition.children) {
                return [];
            }
            return this.condition.children.sort((child1, child2) => { return child1.position - child2.position; });
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
                this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
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
            this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
        },
        onAddChildClick() {
            this.createCondition(OR_CONTAINER_NAME, this.nextPosition);
        },
        createCondition(type, position) {
            const condition = Object.assign(
                this.conditionAssociations.create(),
                {
                    type: type,
                    parentId: this.condition.id,
                    position: position
                }
            );
            this.condition.children.push(condition);
        },
        createPlaceholderBefore(element) {
            const originalPosition = element.position;
            this.condition.children.forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(PLACEHOLDER_NAME, originalPosition);
        },
        createPlaceholderAfter(element) {
            const originalPosition = element.position;
            this.condition.children.forEach(child => {
                if (child.position <= originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(PLACEHOLDER_NAME, originalPosition + 1);
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
            const originalPosition = condition.position;
            this.condition.children.forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position -= 1;
            });

            condition.delete();
            this.condition.children.splice(this.condition.children.indexOf(condition), 1);

            if (this.condition.children.length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
                    }
                });
            }
        }
    }
});
