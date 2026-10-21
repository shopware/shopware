import template from './sw-fixture-detail.html.twig';

Shopware.Component.override('sw-fixture-detail', {
    template,

    inject: ['acl', 'systemConfigApiService'],

    methods: {
        onSave() {
            return false;
        },

        onExtensionSave() {
            return this.onSave();
        },
    },
});
