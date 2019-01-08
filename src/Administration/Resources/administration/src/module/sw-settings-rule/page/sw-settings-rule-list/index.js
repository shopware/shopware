import { Component, Mixin, State } from 'src/core/shopware';
import './sw-settings-rule-list.less';
import template from './sw-settings-rule-list.html.twig';

Component.register('sw-settings-rule-list', {
    template,

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            rules: [],
            showDeleteModal: false,
            isLoading: false
        };
    },

    computed: {
        ruleStore() {
            return State.getStore('rule');
        },

        filters() {
            return [];
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();

            this.rules = [];

            return this.ruleStore.getList(params).then((response) => {
                this.total = response.total;
                this.rules = response.items;
                this.isLoading = false;

                return this.rules;
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onBulkDelete() {
            this.showDeleteModal = true;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            this.isLoading = true;
            return this.ruleStore.getById(id).delete(true).then(() => {
                this.isLoading = false;
                return this.getList();
            });
        },

        onConfirmBulkDelete() {
            this.showDeleteModal = false;

            const selectedRules = this.$refs.ruleGrid.getSelection();

            if (!selectedRules) {
                return;
            }

            this.isLoading = true;

            Object.values(selectedRules).forEach((rule) => {
                rule.delete();
            });

            this.ruleStore.sync(true).then(() => {
                this.isLoading = false;
                return this.getList();
            });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onDuplicate(id) {
            const newRule = this.ruleStore.duplicate(id);

            this.$router.push(
                {
                    name: 'sw.settings.rule.detail',
                    params: {
                        id: newRule.id,
                        parentId: id
                    }
                }
            );
        }
    }
});
