import { Component, State, Mixin } from 'src/core/shopware';
import template from './sw-settings-snippet-set-list.html.twig';
import './sw-settings-snippet-set-list.less';

Component.register('sw-settings-snippet-set-list', {
    template,

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('sw-settings-list')
    ],

    data() {
        return {
            isLoading: false,
            snippetSets: [],
            offset: 0
        };
    },

    computed: {
        snippetSetStore() {
            return State.getStore('snippet_set');
        }
    },

    methods: {
        getList() {
            this.isLoading = true;
            const params = this.getListingParams();
            this.snippetSetStore.getList(params).then((response) => {
                this.total = response.total;
                this.snippetSets = response.items;
                this.isLoading = false;

                return this.snippetSets;
            });
        },

        onAddSnippetSet() {
            const snippetSet = this.snippetSetStore.create();
            snippetSet.baseFile = 'en_GB.json';
            snippetSet.iso = 'en_GB';
            this.snippetSets.splice(0, 0, snippetSet);

            const foundRow = this.$refs.snippetSetList.$children.find((item) => {
                return item.$options.name === 'sw-grid-row';
            });

            if (!foundRow) {
                return false;
            }

            foundRow.isEditingActive = true;
            this.$refs.nameEditor.$el.focus();

            return true;
        }
    }
});
