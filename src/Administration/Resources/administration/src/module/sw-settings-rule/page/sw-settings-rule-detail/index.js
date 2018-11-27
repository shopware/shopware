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
            conditions: [],
            duplicate: false
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

            this.ruleStore.apiService.getRuleConditions(this.conditionId).then(
                (response) => {
                    this.conditions = response.data;
                    console.log(this.conditions);
                }
            );
        },

        onSave() {
            const titleSaveSuccess = this.$tc('sw-settings-rule.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-rule.detail.messageSaveSuccess',
                0,
                { name: this.rule.name }
            );

            return this.rule.save().then(() => {
                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            });
        }
    }
});
