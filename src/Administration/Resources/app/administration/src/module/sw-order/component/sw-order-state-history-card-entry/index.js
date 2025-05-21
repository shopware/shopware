import './sw-order-state-history-card-entry.scss';
import template from './sw-order-state-history-card-entry.html.twig';

/**
 * @sw-package checkout
 *
 * @deprecated tag:v6.8.0 - will be removed, no usages found
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['stateStyleDataProviderService'],

    props: {
        history: {
            type: Array,
            required: true,
        },
        transitionOptions: {
            type: Array,
            required: true,
        },
        stateMachineName: {
            type: String,
            required: true,
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        dateFilter() {
            return Shopware.Filter.getByName('date');
        },
    },

    methods: {
        userDisplayName(user) {
            return `${this.$tc('sw-order.stateCard.labelLastEditedBy')} ${user.username}`;
        },

        integrationDisplayName(integration) {
            return this.$t('sw-order.stateCard.labelLastEditedByIntegration', { integrationName: integration.label });
        },

        getDisplayName(historyEntry) {
            if (historyEntry.user !== null) {
                return this.userDisplayName(historyEntry.user);
            }
            if (historyEntry.integration !== null) {
                return this.integrationDisplayName(historyEntry.integration);
            }
            return this.$tc('sw-order.stateCard.labelSystemUser');
        },

        getIconFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).icon;
        },

        getIconColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconStyle;
        },

        getBackgroundColorFromState(stateName) {
            return this.stateStyleDataProviderService.getStyle(this.stateMachineName, stateName).iconBackgroundStyle;
        },
    },
};
