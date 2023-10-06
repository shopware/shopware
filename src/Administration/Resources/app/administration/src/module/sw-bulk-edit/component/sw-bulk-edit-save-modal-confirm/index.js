/**
 * @package system-settings
 */
import template from './sw-bulk-edit-save-modal-confirm.html.twig';
import './sw-bulk-edit-save-modal-confirm.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    props: {
        itemTotal: {
            required: true,
            type: Number,
        },
        /**
        * {
        *     ...
        *     orderDeliveries: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     },
        *     orderTransactions: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     },
        *     orders: {
        *         isChanged: true,
        *         type: 'overwrite',
        *         value: 'cancel'
        *     }
        *     ...
        * }
        */
        bulkEditData: {
            type: Object,
            required: false,
            default: () => {
                return {};
            },
        },
    },

    computed: {
        isFlowTriggered: {
            get() {
                return Shopware.State.get('swBulkEdit').isFlowTriggered;
            },
            set(isFlowTriggered) {
                Shopware.State.commit('swBulkEdit/setIsFlowTriggered', isFlowTriggered);
            },
        },

        triggeredFlows() {
            const triggeredFlows = [];

            Object.entries(this.bulkEditData).forEach(([key, value]) => {
                if (key === this.$tc(`sw-bulk-edit.modal.confirm.triggeredFlows.${key}.key`) && value.isChanged === true) {
                    triggeredFlows.push(this.$tc(`sw-bulk-edit.modal.confirm.triggeredFlows.${key}.label`));
                }
            });

            return triggeredFlows;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
        },

        setTitle() {
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.confirm.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'cancel',
                    label: this.$tc('global.sw-modal.labelClose'),
                    position: 'left',
                    action: '',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-bulk-edit.modal.confirm.buttons.applyChanges'),
                    position: 'right',
                    variant: 'primary',
                    action: 'process',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },
    },
};
