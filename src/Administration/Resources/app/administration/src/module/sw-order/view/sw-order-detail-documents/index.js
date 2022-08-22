import template from './sw-order-detail-documents.html.twig';

const { Component } = Shopware;

const { mapGetters, mapState } = Shopware.Component.getComponentHelper();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-order-detail-documents', {
    template,

    computed: {
        ...mapGetters('swOrderDetail', [
            'isLoading',
        ]),

        ...mapState('swOrderDetail', [
            'order',
            'versionContext',
        ]),
    },

    methods: {
        saveAndReload() {
            this.$emit('save-and-reload');
        },

        onUpdateLoading(loading) {
            this.$emit('update-loading', loading);
        },
    },
});
