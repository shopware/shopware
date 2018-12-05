import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-rule-detail.html.twig';

Component.register('sw-settings-rule-detail', {
    template,

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            rule: {},
            duplicate: false,
            nestedConditions: []
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

            this.ruleId = this.$route.params.id;
            this.rule = this.ruleStore.getById(this.ruleId);
            this.conditionId = this.ruleId;

            if (this.$route.params.parentId) {
                this.duplicate = true;
                this.conditionId = this.$route.params.parentId;
            }

            this.rule.id = this.conditionId;
            this.rule.getAssociation('conditions').getList({
                page: 1,
                limit: 500
            }).then(() => {
                this.nestedConditions = this.buildNestedConditions(this.rule.conditions, null)[0];
                if (this.duplicate) {
                    this.rule.conditions.forEach((condition) => {
                        condition.id = null;
                        condition.parentId = null;
                    });

                    this.rule.id = this.ruleId;
                }
            });
        },

        buildNestedConditions(conditions, parentId) {
            const nested = [];
            conditions.forEach((condition) => {
                if (condition.parentId === parentId) {
                    const children = this.buildNestedConditions(conditions, condition.id);
                    children.forEach((child) => {
                        condition.children.push(child);
                    });

                    nested.push(condition);
                }
            });

            return nested;
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-settings-rule.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-rule.detail.messageSaveSuccess',
                0,
                { name: this.rule.name }
            );

            this.rule.conditions = this.nestedConditions;

            return this.rule.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
