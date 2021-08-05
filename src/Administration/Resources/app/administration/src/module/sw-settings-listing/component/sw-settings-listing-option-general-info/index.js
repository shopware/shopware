import template from './sw-settings-listing-option-general-info.html.twig';

const { Component } = Shopware;
const { mapPropertyErrors } = Component.getComponentHelper();

Component.register('sw-settings-listing-option-general-info', {
    template,

    model: {
        prop: 'sortingOption',
        event: 'input',
    },

    props: {
        sortingOption: {
            type: Object,
            required: true,
        },

        isDefaultSorting: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        ...mapPropertyErrors('sortingOption', [
            'label',
        ]),
    },
});
