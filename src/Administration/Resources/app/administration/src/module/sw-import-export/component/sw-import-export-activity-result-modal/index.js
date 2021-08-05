import template from './sw-import-export-activity-result-modal.html.twig';

Shopware.Component.register('sw-import-export-activity-result-modal', {
    template,

    props: {
        result: {
            type: Array,
            required: false,
            default() {
                return [];
            },
        },
    },
});
