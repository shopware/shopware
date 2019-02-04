import { Component, Mixin } from 'src/core/shopware';
import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.scss';

const PLACEHOLDER_NAME = 'placeholder';
const OR_CONTAINER_NAME = 'orContainer';
const AND_CONTAINER_NAME = 'andContainer';

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

    inject: ['ruleConditionDataProviderService'],
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

    data() {
        return {
            disabledDeleteButton: false,
            detailPage: undefined
        };
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
            this.locateDetailPage();
            if (this.detailPage) {
                this.detailPage.$on('check-delete-all', () => {
                    this.disabledDeleteButton = this.onCheckDeleteAll();
                });
            }
            this.condition.value = {};

            if (typeof this.condition.children === 'undefined') {
                this.condition.children = [];
                return;
            }

            if (!this.condition.children.length) {
                this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
            }
        },
        onCheckDeleteAll() {
            if (this.level > 1) {
                return false;
            }

            if (this.level === 1) {
                const parent = this.conditionAssociations.getById(this.condition.parentId);

                if (parent.children.length === 1
                    && this.condition.type === AND_CONTAINER_NAME
                    && this.condition.children.length === 1
                    && this.condition.children[0].type === PLACEHOLDER_NAME) {
                    return true;
                }
            }

            if (this.level === 0
                && this.condition.children.length === 1
                && this.condition.children[0].type === AND_CONTAINER_NAME
                && this.condition.children[0].children.length === 1
                && this.condition.children[0].children[0].type === PLACEHOLDER_NAME) {
                return true;
            }

            return false;
        },
        onFinishLoading() {
            this.createComponent();
        },
        getComponent(type) {
            const condition = this.ruleConditionDataProviderService.getByType(type);
            if (!condition) {
                return 'sw-condition-not-found';
            }

            this.$nextTick(() => {
                this.emitConditionContainerChangeEvent();
            });
            return condition.component;
        },
        onAddAndClick() {
            this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
            this.emitConditionContainerChangeEvent();
        },
        onAddChildClick() {
            this.createCondition(OR_CONTAINER_NAME, this.nextPosition);
            this.emitConditionContainerChangeEvent();
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

            this.emitConditionContainerChangeEvent();
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

            if (this.condition.children.length === 1) {
                this.onDeleteAll();
                return;
            }

            condition.delete();
            this.condition.children.splice(this.condition.children.indexOf(condition), 1);

            this.emitConditionContainerChangeEvent();

            if (this.condition.children.length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createCondition(PLACEHOLDER_NAME, this.nextPosition);
                    }
                });
            }
        },
        locateDetailPage() {
            let parent = this.$parent;

            while (parent) {
                if (['sw-settings-rule-create', 'sw-settings-rule-detail'].includes(parent.$options.name)) {
                    this.detailPage = parent;
                    return;
                }

                parent = parent.$parent;
            }
        },
        emitConditionContainerChangeEvent() {
            if (!this.detailPage) {
                return;
            }

            this.detailPage.$emit(
                'check-delete-all',
                {
                    condition: this.condition,
                    level: this.level
                }
            );
        }
    }
});
