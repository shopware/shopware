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
            nestedConditions: {},
            treeConfig: {
                conditionStore: {},
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
                    type: 'placeholder'
                },
                getComponent(condition) {
                    condition = this.conditionStore.getById(condition.type);
                    if (!condition.component) {
                        return 'sw-condition-not-found';
                    }

                    return condition.component;
                },
                isAndContainer(condition) { return condition.type === 'andContainer'; },
                isOrContainer(condition) { return condition.type === 'orContainer'; },
                isPlaceholder(condition) { return condition.type === 'placeholder'; }
            }
        };
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
        },
        moduleTypeStore() {
            const moduleTypes = this.ruleConditionDataProviderService.moduleTypes;
            const modules = [];
            moduleTypes.forEach((type) => {
                const moduleName = this.$tc(`sw-settings-rule.detail.types.${type}`);
                modules.push({
                    id: type,
                    name: moduleName
                });
            });

            return new LocalStore(modules, 'id');
        }
    },

    watch: {
        'rule.moduleTypes': {
            immediate: true,
            deep: true,
            handler() {
                if (!this.rule.moduleTypes || (this.rule.moduleTypes && !this.rule.moduleTypes.types)) {
                    this.rule.moduleTypes = { types: [] };
                }

                this.treeConfig.conditionStore = new LocalStore(
                    this.ruleConditionDataProviderService.getConditions((condition) => {
                        condition.meta = {
                            viewData: {
                                label: this.$tc(condition.label),
                                type: this.$tc(condition.label)
                            }
                        };
                    }, this.rule.moduleTypes.types),
                    'type'
                );
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.$route.params.id) {
                return;
            }

            this.rule = this.ruleStore.getById(this.$route.params.id);
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

            if (this.rule.moduleTypes && this.rule.moduleTypes.types.length === 0) {
                this.rule.moduleTypes = null;
            }

            this.removeOriginalConditionTypes(this.rule.conditions);

            return this.rule.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
                this.$refs.conditionTree.$emit('on-save');

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
                if (changes && condition.isDeleted !== true) {
                    condition.original.type = '';
                    condition.original.value = {};
                }
            });
        }
    }
});
