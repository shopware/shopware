import template from './debounce-component.html.twig';

const { debounce } = Shopware.Utils;

Shopware.Component.register('sw-debounce', {
    template,

    data() {
        return {
            query: '',
        };
    },

    methods: {
        onInput(value) {
            this.query = value;
            this.searchDebounce();
        },

        doSearch() {
            this.query = this.query.trim();
        },

        searchDebounce: debounce(function onSearch() {
            this.doSearch();
        }, 300),
    },
});
