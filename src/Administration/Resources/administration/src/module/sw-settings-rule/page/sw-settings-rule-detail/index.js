import { Component, State, Mixin } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import template from './sw-settings-rule-detail.html.twig';
import './sw-settings-rule-detail.scss';
import SwSelect from '../../../../app/component/form/sw-select';


Component.register('sw-settings-rule-detail', {
    template,

    inject: ['ruleConditionDataProviderService'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('rule')
    ],

    components: {
        SwSelect
    },

    data() {
        return {
            rule: {},
            nestedConditions: {},
            conditionAssociations: {}
        };
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
        },
        ruleConditionStore() {
            return State.getStore('ruleCondition');
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

            this.conditionAssociations = this.rule.getAssociation('conditions');
            this.conditionAssociations.getList({
                page: 1,
                limit: 500,
                sortBy: 'position'
            }).then(() => {
                this.nestedConditions = this.buildNestedConditions(this.rule.conditions, null);

                this.$nextTick(() => {
                    this.$refs.mainContainer.$emit('finish-loading', this.nestedConditions);
                });
            });
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
