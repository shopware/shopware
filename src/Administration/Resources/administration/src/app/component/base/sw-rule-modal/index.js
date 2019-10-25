import template from './sw-rule-modal.html.twig';
import './sw-rule-modal.scss';

const { Component, Mixin, State } = Shopware;
const { warn } = Shopware.Utils.debug;
const { LocalStore } = Shopware.DataDeprecated;

/**
 * @status ready
 * @description The <u>sw-rule-modal</u> component is used to create or modify a rule.
 * @example-type code-only
 * @component-example
 * <sw-rule-modal ruleId="0fd38734776f41e9a1ba431f1667e677" @save="onSave" @modal-close="onCloseModal">
 * </sw-rule-modal>
 */
Component.register('sw-rule-modal', {
    template,

    inject: ['ruleConditionDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('placeholder')
    ],

    props: {
        ruleId: {
            type: String,
            required: false,
            default: null
        },
        allowedRuleScopes: {
            type: Array,
            required: false,
            default: null
        }
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
        },
        modalTitle() {
            if (!this.ruleId) {
                return this.$tc('sw-rule-modal.modalTitleNew');
            }
            return this.placeholder(this.rule, 'name', this.$tc('sw-rule-modal.modalTitleModify'));
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
                }, this.allowedRuleScopes), 'type'),
                entityName: 'rule',
                conditionIdentifier: 'conditions',
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
                isDataSet(condition) { return this.dataCheckMethods[condition.type](condition); }
            }
        };
    },

    beforeMount() {
        this.beforeMountComponent();
    },

    methods: {
        beforeMountComponent() {
            this.loadEntityData();
        },
        loadEntityData() {
            if (this.ruleId !== null) {
                this.ruleStore.getByIdAsync(this.ruleId).then(rule => {
                    this.rule = rule;
                    this.isLoaded = true;
                });
            } else {
                this.rule = this.ruleStore.create();
                this.isLoaded = true;
            }
        },
        saveAndClose() {
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc(
                'sw-rule-modal.messageSaveSuccess',
                0,
                { name: this.rule.name }
            );

            const titleSaveError = this.$tc('global.default.error');
            const messageSaveError = this.$tc(
                'sw-rule-modal.messageSaveError', 0, { name: this.rule.name }
            );

            if (this.conditionsClientValidation(this.rule.conditions, false)) {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, 'client validation failure');
                this.$refs.conditionTree.$emit('entity-save', false);

                return null;
            }

            this.removeOriginalConditionTypes(this.rule.conditions);

            return this.rule.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });

                this.emitSave();
                this.$emit('modal-close');
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });

                warn(this._name, exception.message, exception.response);
                this.$refs.conditionTree.$emit('entity-save');
            });
        },

        emitSave() {
            this.$emit('save', this.rule);
        },

        removeOriginalConditionTypes(conditions) {
            conditions.forEach((condition) => {
                if (condition.children) {
                    this.removeOriginalConditionTypes(condition.children);
                }

                const changes = Object.keys(condition.getChanges()).length;
                if (condition.isDeleted === false
                    && (changes || !this.areConditionsValueEqual(condition, condition.original))) {
                    condition.original.type = '';
                    condition.original.value = {};
                }
            });
        },

        areConditionsValueEqual(conditionA, conditionB) {
            if (!(conditionA.value && conditionB.value)) {
                return true;
            }

            const propsA = Object.keys(conditionA.value);
            const propsB = Object.keys(conditionB.value);

            if (propsA.length !== propsB.length) {
                return false;
            }

            return !propsA.find(property => {
                return conditionA.value[property].toString() !== conditionB.value[property].toString();
            });
        },

        conditionsClientValidation(conditions, error) {
            conditions.forEach((condition) => {
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
        }
    }
});
