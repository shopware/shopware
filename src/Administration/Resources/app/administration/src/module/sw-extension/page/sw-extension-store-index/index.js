import template from './sw-extension-store-index.html.twig';
import './sw-extension-store-index.scss';

const { Component } = Shopware;

/**
 * @private
 */
Component.register('sw-extension-store-index', {
    template,

    props: {
        id: {
            type: String,
            required: false,
            default: null
        }
    },

    computed: {
        activeFilters() {
            return Shopware.State.get('shopwareExtensions').search.filter;
        },

        isTheme() {
            const isTheme = this.$route.name.includes('theme');

            return isTheme ? 'themes' : 'apps';
        }
    },

    watch: {
        isTheme: {
            immediate: true,
            handler(newValue) {
                this.$set(this.activeFilters, 'group', newValue);
            }
        }
    },

    methods: {
        updateSearch(term) {
            Shopware.State.commit('shopwareExtensions/setSearchValue', { key: 'term', value: term });
        }
    }
});
