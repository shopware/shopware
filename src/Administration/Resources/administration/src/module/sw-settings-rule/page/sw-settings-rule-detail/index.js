import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-settings-rule-detail.html.twig';
import './sw-settings-rule-detail.scss';

Component.register('sw-settings-rule-detail', {
    template,

    inject: ['ruleConditionDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('rule')
    ],

    data() {
        return {
            rule: {},
            moduleTypes: null,
            nestedConditions: {},
            moduleTypeStore: new LocalStore(this.ruleConditionDataProviderService.getModuleTypes((moduleType) => {
                if (moduleType.label) {
                    return;
                }

                moduleType.name = this.$tc(moduleType.name);
                moduleType.label = moduleType.name;
            }), 'id'),
            treeConfig: {
                conditionStore: new LocalStore(this.ruleConditionDataProviderService.getConditions((condition) => {
                    condition.translated = {
                        label: this.$tc(condition.label),
                        type: this.$tc(condition.label)
                    };
                }), 'type'),
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

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.rule.name || '';
        },

        ruleStore() {
            return State.getStore('rule');
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        'rule.moduleTypes': {
            deep: true,
            handler() {
                this.checkModuleType();
            }
        }
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.rule = this.ruleStore.getById(this.$route.params.id);
        },

        checkModuleType() {
            if (!this.rule.moduleTypes || (this.rule.moduleTypes && !this.rule.moduleTypes.types)) {
                this.moduleTypes = [];
                return;
            }

            this.moduleTypes = this.rule.moduleTypes.types;
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-settings-rule.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-rule.detail.messageSaveSuccess',
                0,
                { name: this.rule.name }
            );

            const titleSaveError = this.$tc('sw-settings-rule.detail.titleSaveError');
            const messageSaveError = this.$tc(
                'sw-settings-rule.detail.messageSaveError', 0, { name: this.rule.name }
            );

            if (this.moduleTypes.length === 0) {
                this.rule.moduleTypes = null;
            } else {
                this.rule.moduleTypes = { types: this.moduleTypes };
            }

            if (this.conditionsClientValidation(this.rule.conditions, false)) {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, 'client validation failure');
                this.$refs.conditionTree.$emit('on-save');

                return null;
            }

            this.removeOriginalConditionTypes(this.rule.conditions);

            return this.rule.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
                this.$refs.conditionTree.$emit('on-save');

                this.checkModuleType();

                return true;
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
                this.$refs.conditionTree.$emit('on-save');
            });
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

        conditionsClientValidation(conditions, error) {
            conditions.forEach((condition) => {
                if (condition.children) {
                    error = this.conditionsClientValidation(condition.children, error);
                }

                if (this.treeConfig.isAndContainer(condition) || this.treeConfig.isOrContainer(condition)) {
                    return;
                }

                if (condition.errors.map(obj => obj.id).includes('clientValidationError')) {
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
        }
    }
});
