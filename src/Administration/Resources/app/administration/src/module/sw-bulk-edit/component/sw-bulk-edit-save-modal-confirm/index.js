import template from './sw-bulk-edit-save-modal-confirm.html.twig';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-confirm', {
    template,

    props: {
        itemTotal: {
            required: true,
            type: Number,
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
