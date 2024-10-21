import './sw-order-state-history-card-entry.scss';
import template from './sw-order-state-history-card-entry.html.twig';

/**
 * @package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

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

        /**
         * @deprecated tag:v6.7.0 - Can be removed. Event listerns will be in $attrs.
         */
        listeners() {
            let listeners = {};

            if (this.isCompatEnabled('INSTANCE_LISTENERS')) {
                listeners = this.$listeners;
            }

            return listeners;
        },
    },

    methods: {
        userDisplayName(user) {
            let userString = '';
            if (user === null) {
                userString = this.$tc('sw-order.stateCard.labelSystemUser');
            } else {
                userString = user.username;
            }

            return `${this.$tc('sw-order.stateCard.labelLastEditedBy')} ${userString}`;
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
