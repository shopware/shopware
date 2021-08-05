import template from './sw-bulk-edit-save-modal-error.html.twig';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-error', {
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
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.error.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'log',
                    label: this.$tc('sw-bulk-edit.modal.error.buttons.openLog'),
                    position: 'right',
                    disabled: false,
                },
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
