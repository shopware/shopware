import template from './sw-settings-listing-option-general-info.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-settings-listing-option-general-info', {
    template,

    props: {
        sortingOption: {
            type: Object,
            required: true
        },

        isDefaultSorting: {
            type: Boolean,
            required: true
        }
    },

    model: {
        prop: 'sortingOption',
        event: 'input'
    },

    computed: {
        ...mapPropertyErrors('sortingOption', [
            'label'
        ])
    }
});
