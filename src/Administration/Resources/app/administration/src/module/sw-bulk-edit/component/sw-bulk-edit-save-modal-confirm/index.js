import template from './sw-bulk-edit-save-modal-confirm.html.twig';
import './sw-bulk-edit-save-modal-confirm.scss';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-confirm', {
    template,

    inject: ['feature'],

    props: {
        itemTotal: {
            required: true,
            type: Number,
        },
        bulkEditData: {
            type: Object,
            required: true,
        },
    },

    data() {
        return {
            isFlowTriggered: true,
        };
    },

    computed: {
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
});
