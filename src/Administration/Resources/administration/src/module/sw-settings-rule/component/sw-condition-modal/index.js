import LocalStore from 'src/core/data/LocalStore';
import template from './sw-condition-modal.html.twig';
import './sw-condition-modal.scss';

const { Component, StateDeprecated, Mixin } = Shopware;
const { warn } = Shopware.Utils.debug;

Component.register('sw-condition-modal', {
    template,

    inject: ['ruleConditionDataProviderService', 'entityAssociationStore'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        condition: {
            type: Object,
            required: true
        }
    },

    computed: {
        ruleStore() {
            return StateDeprecated.getStore('rule');
        }
    },

    data() {
        return {
            rule: {},
            isLoaded: false,
            // TODO: Optimize treeConfig --> defaultConfig in sw-condition-tree
            treeConfig: {
                conditionStore: new LocalStore(this.ruleConditionDataProviderService.getConditions((condition) => {
                    condition.translated = {
                        label: this.$tc(condition.label),
                        type: this.$tc(condition.label)
                    };
                }, ['lineItem']), 'type'),
                entityName: 'rule',
                conditionIdentifier: 'children',
                childName: 'children',
                andContainer: {
                    type: 'andContainer'
                },
                orContainer: {
                    type: 'orContainer'
                },
                placeholder: {
                    type: null
                },
                dataCheckMethods: {},
                getComponent(condition) {
                    if (this.isPlaceholder(condition)) {
                        return 'sw-condition-base';
                    }
                    if (this.isAndContainer(condition)) {
                        return 'sw-condition-and-container';
                    }
                    if (this.isOrContainer(condition)) {
                        return 'sw-condition-or-container';
                    }

                    condition = this.conditionStore.getById(condition.type);
                    if (!condition.component) {
                        return 'sw-condition-not-found';
                    }

                    return condition.component;
                },
                isAndContainer(condition) { return condition.type === 'andContainer'; },
                isOrContainer(condition) { return condition.type === 'orContainer'; },
                isPlaceholder(condition) { return !condition.type; },
                isDataSet(condition) {
                    return typeof this.dataCheckMethods[condition.type] !== 'function'
                    || this.dataCheckMethods[condition.type](condition);
                }
            }
        };
    },

    methods: {
        closeModal() {
            const titleSaveError = this.$tc('sw-settings-rule.conditionModal.titleSaveError');
            const messageSaveError = this.$tc('sw-settings-rule.conditionModal.messageSaveError');

            if (this.conditionsClientValidation(this.flat(this.condition.children), false)) {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, 'client validation failure');
                this.$refs.conditionTree.$emit('entity-save');

                return;
            }

            this.$emit('modal-close');
        },
        flat(conditions) {
            const flattedConditions = [...conditions];

            conditions.forEach(condition => {
                flattedConditions.push(...this.flat(condition.children));
            });

            return flattedConditions;
        },
        conditionsClientValidation(conditions, error) {
            conditions.forEach((condition) => {
                if (condition.isDeleted) {
                    return;
                }

                if (this.hasDeletedParent(condition.parentId, conditions)) {
                    condition.remove();
                    return;
                }

                if (condition.children) {
                    error = this.conditionsClientValidation(condition.children, error);
                }

                if (this.treeConfig.isAndContainer(condition) || this.treeConfig.isOrContainer(condition)) {
                    return;
                }

                if (condition.errors.map(obj => obj.id).includes('clientValidationError')) {
                    error = true;
                    return;
                }

                if (this.treeConfig.isPlaceholder(condition)) {
                    condition.errors.push({
                        id: 'clientValidationError',
                        type: 'placeholder'
                    });

                    error = true;
                }

                if (!this.treeConfig.isDataSet(condition)) {
                    condition.errors.push({
                        id: 'clientValidationError',
                        type: 'data'
                    });

                    error = true;
                }
            });

            return error;
        },
        hasDeletedParent(parentId, conditions) {
            if (!parentId) {
                return false;
            }

            const parent = conditions.find(condition => condition.id === parentId);

            if (!parent) {
                return false;
            }

            return parent.isDeleted || this.hasDeletedParent(parent.parentId, conditions);
        },
        deleteAndClose() {
            this.deleteChildren(this.condition.children);
            this.closeModal();
            this.condition.children = [];
        },
        deleteChildren(children) {
            children.forEach((child) => {
                child.delete();
            });
        }
    }
});
