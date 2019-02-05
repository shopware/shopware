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
            conditionStore: {},
            treeConfig: {
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
                getComponent: (condition, callback) => {
                    condition = this.conditionStore.getById(condition.type);
                    if (!condition) {
                        return 'sw-condition-not-found';
                    }

                    if (callback) {
                        this.$nextTick(() => {
                            callback(condition.component);
                        });
                    }
                    return condition.component;
                },
                isAndContainer: (condition) => condition.type === 'andContainer',
                isOrContainer: (condition) => condition.type === 'orContainer',
                isPlaceholder: (condition) => condition.type === 'placeholder'
            }
        };
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
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
            const conditions = this.ruleConditionDataProviderService.getConditions();
            Object.keys(conditions).forEach(key => {
                conditions[key].meta = {
                    viewData: {
                        label: this.$tc(conditions[key].label),
                        name: this.$tc(conditions[key].label)
                    }
                };
                conditions[key].name = key;
            });
            this.conditionStore = new LocalStore(this.ruleConditionDataProviderService.getConditions(), 'name');
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

            // todo: this.rule.conditions = [this.nestedConditions]; check if needed
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
