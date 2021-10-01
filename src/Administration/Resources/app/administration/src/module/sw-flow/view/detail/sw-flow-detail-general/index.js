import template from './sw-flow-detail-general.html.twig';
import './sw-flow-detail-general.scss';

const { Component } = Shopware;
const { mapPropertyErrors, mapState } = Component.getComponentHelper();

Component.register('sw-flow-detail-general', {
    template,

    inject: ['acl'],

    props: {
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
        isNewFlow: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        logGridColumns() {
            return [
                {
                    property: 'dataExecuted',
                    label: this.$tc('sw-flow.detail.labelLogDateExecuted'),
                    naturalSorting: true,
                    primary: true,
                },
                {
                    property: 'trigger',
                    label: this.$tc('sw-flow.detail.labelLogTrigger'),
                    sortable: false,
                },
                {
                    property: 'triggerValue',
                    label: this.$tc('sw-flow.detail.labelLogTriggerValue'),
                    sortable: false,
                },
                {
                    property: 'actions',
                    label: this.$tc('sw-flow.detail.labelLogActions'),
                    sortable: false,
                },
                {
                    property: 'success',
                    label: this.$tc('sw-flow.detail.labelLogSuccess'),
                    sortable: false,
                },
            ];
        },

        ...mapState('swFlowState', ['flow']),
        ...mapPropertyErrors('flow', ['name']),
    },
});
