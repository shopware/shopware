import template from './sw-bulk-edit-save-modal-success.html.twig';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-success', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
        },

        setTitle() {
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.success.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'close',
                    label: this.$tc('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: false,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },
    },
});

