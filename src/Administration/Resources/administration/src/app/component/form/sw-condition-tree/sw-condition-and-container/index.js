import template from './sw-condition-and-container.html.twig';
import './sw-condition-and-container.scss';

const { Component, Mixin } = Shopware;

/**
 * @private
 * @description Contains some sw-base-conditions / sw-condition-and-container connected by and.
 * This component must be a child of sw-condition-tree
 * @status prototype
 * @example-type code-only
 * @component-example
 * <sw-condition-and-container :condition="condition" :level="0"></sw-condition-and-container>
 */
Component.register('sw-condition-and-container', {
    template,

    inject: ['config', 'entityAssociationStore', 'isApi'],

    mixins: [
        Mixin.getByName('validation'),
        Mixin.getByName('notification')
    ],

    props: {
        condition: {
            type: Object,
            required: false,
            default: null
        },
        level: {
            type: Number,
            required: true
        },
        parentDisabledDelete: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        containerRowClass() {
            return this.level % 2 ? 'container-condition-level__is--odd' : 'container-condition-level__is--even';
        },
        nextPosition() {
            const children = this.condition[this.config.childName];
            if (!children || !children.length) {
                return 1;
            }

            return children[children.length - 1].position + 1;
        },
        sortedChildren() {
            if (!this.condition[this.config.childName]) {
                return [];
            }
            return this.filterDeletedChildren(this.condition)
                .sort((child1, child2) => { return child1.position - child2.position; });
        },
        disabledDeleteButton() {
            if (this.level === 1) {
                return this.parentDisabledDelete;
            }

            if (this.level > 0 || !this.condition || !this.condition[this.config.childName]) {
                return false;
            }

            const firstLevelChildren = this.filterDeletedChildren(this.condition);

            if (firstLevelChildren.length !== 1 || !this.config.isAndContainer(firstLevelChildren[0])) {
                return false;
            }

            const secondLevelChildren = this.filterDeletedChildren(firstLevelChildren[0]);

            return (secondLevelChildren.length === 1 && this.config.isPlaceholder(secondLevelChildren[0]));
        }
    },

    watch: {
        condition() {
            this.createFirstPlaceholderIfNecessary();
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.createFirstPlaceholderIfNecessary();
        },
        createFirstPlaceholderIfNecessary() {
            if (!this.condition[this.config.childName]) {
                this.condition[this.config.childName] = [];
                return;
            }

            if (!this.condition[this.config.childName].length) {
                this.createCondition(this.config.placeholder, this.nextPosition);
            }
        },
        getComponent(condition) {
            return this.config.getComponent(condition);
        },
        onAddAndClick() {
            this.createCondition(this.config.placeholder, this.nextPosition);
        },
        onAddChildClick() {
            this.createCondition(this.config.orContainer, this.nextPosition);
        },
        createCondition(conditionData, position) {
            const condition = Object.assign(
                this.entityAssociationStore().create(),
                conditionData,
                {
                    parentId: this.condition.id,
                    position: position
                }
            );
            this.condition[this.config.childName].push(condition);
        },
        createPlaceholderBefore(element) {
            const originalPosition = element.position;
            this.condition[this.config.childName].forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(this.config.placeholder, originalPosition);
        },
        createPlaceholderAfter(element) {
            const originalPosition = element.position;
            this.condition[this.config.childName].forEach(child => {
                if (child.position <= originalPosition) {
                    return;
                }

                child.position += 1;
            });

            this.createCondition(this.config.placeholder, originalPosition + 1);
        },
        onDeleteAll() {
            if (this.level === 0) {
                this.condition[this.config.childName].forEach((child) => {
                    child.delete();
                    this.deleteChildren(child[this.config.childName]);
                });
            } else {
                this.deleteChildren(this.condition[this.config.childName]);
            }

            this.condition[this.config.childName] = [];

            this.$emit('condition-delete', this.condition);
        },
        deleteChildren(children) {
            children.forEach((child) => {
                if (child[this.config.childName].length > 0) {
                    this.deleteChildren(child[this.config.childName]);
                }
                child.remove();
            });
        },
        onDeleteCondition(condition) {
            const originalPosition = condition.position;
            const children = this.filterDeletedChildren(this.condition);
            children.forEach(child => {
                if (child.position < originalPosition) {
                    return;
                }

                child.position -= 1;
            });

            if (children.length === 1) {
                this.onDeleteAll();
                return;
            }

            condition.delete();

            if (children.length <= 0) {
                this.$nextTick(() => {
                    if (this.level === 0) {
                        this.onAddChildClick();
                    } else {
                        this.createCondition(this.config.placeholder, this.nextPosition);
                    }
                });
            }
        },
        filterDeletedChildren(condition) {
            return condition[this.config.childName].filter(child => !child.isDeleted);
        }
    }
});
