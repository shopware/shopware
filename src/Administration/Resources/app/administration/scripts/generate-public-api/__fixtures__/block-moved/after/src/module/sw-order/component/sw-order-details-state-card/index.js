import template from './sw-order-details-state-card.html.twig';

/**
 * @sw-package checkout
 */

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    emits: ['show-status-history'],

    props: {
        entity: {
            type: Object,
            required: true,
        },
        title: {
            type: String,
            required: false,
            default: '',
        },
        stateLabel: {
            type: String,
            required: false,
            default: '',
        },
        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            statesLoading: false,
            stateOptions: [],
            lastStateChange: null,
        };
    },

    computed: {
        entityName() {
            return this.entity.getEntityName();
        },

        lastChangeAuthorLabel() {
            if (this.lastStateChange?.user) {
                return `${this.lastStateChange.user.firstName} ${this.lastStateChange.user.lastName}`;
            }

            return this.$t('sw-order.stateCard.labelSystemUser');
        },
    },

    methods: {
        onShowStatusHistory() {
            this.$emit('show-status-history');
        },

        async onStateSelected(stateType, actionName) {
            if (!stateType || !actionName) {
                return;
            }

            this.statesLoading = true;
            await this.$nextTick();
            this.statesLoading = false;
        },
    },
};
