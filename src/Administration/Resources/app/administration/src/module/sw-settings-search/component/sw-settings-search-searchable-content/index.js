import template from './sw-settings-search-searchable-content.html.twig';
import './sw-settings-search-searchable-content.scss';

const { Component } = Shopware;

Component.register('sw-settings-search-searchable-content', {
    template,

    data() {
        return {
            showExampleModal: false,
            defaultTab: 'general'
        };
    },

    methods: {
        onChangeTab(tabContent) {
            this.defaultTab = tabContent;
        },

        onShowExampleModal() {
            this.showExampleModal = true;
        },

        onCloseExampleModal() {
            this.showExampleModal = false;
        },

        onAddNewConfig() {
            // TODO: NEXT-13010 - Implement "Searchable content" card with API integration
        },

        onResetToDefault() {
            // TODO: NEXT-13010 - Implement "Searchable content" card with API integration
        }
    }
});
