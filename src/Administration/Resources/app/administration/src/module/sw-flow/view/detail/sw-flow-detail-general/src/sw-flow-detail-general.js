import template from './sw-flow-detail-general.html.twig';
import './sw-flow-detail-general.scss';
import { aclService, mapPropertyErrors, mapState, useFlowStore } from '../dependencies';

/**
 * @private
 * @sw-package after-sales
 */
export default {
    template,

    inject: [aclService],

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
        isTemplate: {
            type: Boolean,
            required: false,
            default: false,
        },
        isUnknownTrigger: {
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
                    label: this.$t('sw-flow.detail.labelLogDateExecuted'),
                    naturalSorting: true,
                    primary: true,
                },
                {
                    property: 'trigger',
                    label: this.$t('sw-flow.detail.labelLogTrigger'),
                    sortable: false,
                },
                {
                    property: 'triggerValue',
                    label: this.$t('sw-flow.detail.labelLogTriggerValue'),
                    sortable: false,
                },
                {
                    property: 'actions',
                    label: this.$t('sw-flow.detail.labelLogActions'),
                    sortable: false,
                },
                {
                    property: 'success',
                    label: this.$t('sw-flow.detail.labelLogSuccess'),
                    sortable: false,
                },
            ];
        },

        isFlowTemplate() {
            return this.$route.query?.type === 'template';
        },

        ...mapState(() => useFlowStore(), ['flow']),
        ...mapPropertyErrors('flow', ['name']),
    },
};
