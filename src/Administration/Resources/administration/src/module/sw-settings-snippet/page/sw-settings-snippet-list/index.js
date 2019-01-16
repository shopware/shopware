import { Component, Mixin, State } from 'src/core/shopware';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-settings-snippet-list.html.twig';
import './sw-settings-snippet-list.less';

Component.register('sw-settings-snippet-list', {
    template,

    inject: ['snippetSetService', 'snippetService'],

    mixins: [
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            entityName: 'snippets',
            snippetSets: {},
            metaId: ''
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        },
        queryIdCount() {
            return this.queryIds.length;
        },
        metaName() {
            return this.snippetSets[0].name;
        }
    },

    methods: {
        initializeSnippetSet() {
            if (!this.$route.query.ids || this.$route.query.ids.length <= 0) {
                this.$router.back();

                return;
            }

            this.isLoading = true;
            this.queryIds = this.$route.query.ids;
            const criteria = CriteriaFactory.equalsAny('id', this.queryIds);

            this.snippetSetService.getCustomList(this.page, this.limit).then((response) => {
                this.snippetSetStore.getList({ criteria }).then((sets) => {
                    this.snippetSets = sets.items;
                    this.metaId = this.queryIds[0];
                    this.total = response.total;

                    this.grid = this.prepareGrid(response.data);
                }).then(() => {
                    this.isLoading = false;
                });
            });
        },

        prepareGrid(grid) {
            const result = [];
            Object.values(grid).forEach((items) => {
                const content = {};
                items.forEach((item) => {
                    content[item.setId] = item;
                });
                result.push(content);
            });

            return result;
        },

        getList() {
            this.initializeSnippetSet();
        },

        onEdit(snippet) {
            if (snippet && snippet.id) {
                this.$router.push({
                    name: 'sw.settings.snippet.detail',
                    params: {
                        id: snippet.id
                    }
                });
            }
        },

        onInlineEditSave(result) {
            const responses = [];
            const key = result[this.metaId].translationKey;

            this.snippetSets.forEach((item) => {
                if (result[item.id].value === null) {
                    result[item.id].value = result[item.id].origin;
                }

                if (result[item.id].origin !== result[item.id].value) {
                    responses.push(this.snippetService.save(result[item.id]));
                } else if (result[item.id].id !== null) {
                    responses.push(this.snippetService.delete(result[item.id].id));
                }
            });

            Promise.all(responses).then(() => {
                this.inlineSaveSuccessMessage(key);
                this.getList();
            }).catch(() => {
                this.inlineSaveErrorMessage(key);
                this.getList();
            });
        },

        inlineSaveSuccessMessage(key) {
            const titleSaveSuccess = this.$tc('sw-settings-snippet.list.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-settings-snippet.list.messageSaveSuccess',
                this.queryIdCount,
                { key }
            );

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },

        inlineSaveErrorMessage(key) {
            const titleSaveError = this.$tc('sw-settings-snippet.list.titleSaveError');
            const messageSaveError = this.$tc(
                'sw-settings-snippet.list.messageSaveError',
                this.queryIdCount,
                { key }
            );

            this.createNotificationError({
                title: titleSaveError,
                message: messageSaveError
            });
        },

        onDelete(item) {
            this.showDeleteModal = item;
        },

        onInlineEditCancel(result, upperIndex) {
            // ToDo: @m.Brode && @d.Garding Implement method
            console.log('WIP: InlineCancel ; result: ', result, '; index: ', upperIndex);
        }
    }
});
