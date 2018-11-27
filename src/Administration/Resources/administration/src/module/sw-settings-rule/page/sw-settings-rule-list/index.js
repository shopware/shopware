import { Application, Component, Mixin, State } from 'src/core/shopware';
import ApiService from 'src/core/service/api/api.service';
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
            isLoading: false,
            payload: []
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
            }).catch(this.onCloseDeleteModal());
        },

        onConfirmBulkDelete() {
            this.showDeleteModal = false;

            const selectedRules = this.$refs.ruleGrid.getSelection();

            if (!selectedRules) {
                return;
            }

            this.isLoading = true;

            const apiService = new ApiService(
                Application.getContainer('init').httpClient,
                Application.getContainer('service').loginService,
                'sync'
            );

            this.payload = [];

            Object.keys(selectedRules).forEach((ruleId) => {
                this.payload[ruleId] = {
                    entity: 'rule',
                    action: 'delete',
                    payload: { id: ruleId }
                };
            });

            const headers = apiService.getBasicHeaders();
            const oldBaseUrl = apiService.httpClient.defaults.baseURL;

            apiService.httpClient.defaults.baseURL = apiService.httpClient.defaults.baseURL.slice(
                0,
                apiService.httpClient.defaults.baseURL.lastIndexOf('/')
            );

            apiService.httpClient
                .post(apiService.getApiBasePath(''), Object.assign({}, this.payload), { headers }).then(
                    () => {
                        apiService.httpClient.defaults.baseURL = oldBaseUrl;

                        this.isLoading = false;
                        return this.getList();
                    }
                );

            this.payload = [];
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
