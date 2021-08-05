import template from './sw-shopware-updates-requirements.html.twig';

const { Component } = Shopware;

Component.register('sw-settings-shopware-updates-requirements', {
    template,

    props: {
        updateInfo: {
            type: Object,
            required: true,
            default: () => {},
        },
        requirements: {
            type: Array,
            required: true,
            default: () => [],
        },
        isLoading: {
            type: Boolean,
        },
    },

    data() {
        return {
            columns: [
                {
                    property: 'message',
                    label: this.$t('sw-settings-shopware-updates.requirements.columns.message'),
                    rawData: true,
                },
                {
                    property: 'result',
                    label: this.$t('sw-settings-shopware-updates.requirements.columns.status'),
                    rawData: true,
                },
            ],
        };
    },
});
