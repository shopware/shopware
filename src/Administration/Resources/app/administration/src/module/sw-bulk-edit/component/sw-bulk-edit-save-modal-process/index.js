import template from './sw-bulk-edit-save-modal-process.html.twig';

const { Component } = Shopware;

Component.register('sw-bulk-edit-save-modal-process', {
    template,

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
            this.setTitle();
            this.$emit('changes-apply');
        },

        setTitle() {
            this.$emit('title-set', this.$tc('sw-bulk-edit.modal.process.title'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'cancel',
                    label: this.$tc('sw-bulk-edit.modal.process.buttons.cancel'),
                    position: 'left',
                    action: '',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('global.sw-modal.labelClose'),
                    position: 'right',
                    variant: 'primary',
                    action: '',
                    disabled: true,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },
    },
});
