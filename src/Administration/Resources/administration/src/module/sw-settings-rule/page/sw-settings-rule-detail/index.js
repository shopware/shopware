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

            const ruleId = this.$route.params.id;
            this.rule = this.ruleStore.getById(ruleId);
            let conditionId = ruleId;

            if (this.$route.params.parentId) {
                this.duplicate = true;
                conditionId = this.$route.params.parentId;
            }

            this.rule.id = conditionId;
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

                    this.rule.id = ruleId;
                }
            });
        },

        buildNestedConditions(conditions, parentId) {
            return conditions.reduce((accumulator, current) => {
                if (current.parentId === parentId) {
                    const children = this.buildNestedConditions(conditions, current.id);
                    children.forEach((child) => {
                        current.children.push(child);
                    });

                    accumulator.push(current);
                }

                return accumulator;
            }, []);
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-settings-rule.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-rule.detail.messageSaveSuccess',
                0,
                { name: this.rule.name }
            );

            if (this.duplicate) {
                // todo change conditions for duplicate
            }

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
