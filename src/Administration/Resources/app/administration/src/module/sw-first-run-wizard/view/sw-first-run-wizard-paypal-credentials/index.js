import template from './sw-first-run-wizard-paypal-credentials.html.twig';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-paypal-credentials', {
    template,

    computed: {
        buttonConfig() {
            return [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.paypal.info',
                    disabled: false,
                },
                {
                    key: 'skip',
                    label: this.$tc('sw-first-run-wizard.general.buttonSkip'),
                    position: 'right',
                    variant: null,
                    action: 'sw.first.run.wizard.index.markets',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'sw.first.run.wizard.index.markets',
                    disabled: false,
                },
            ];
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.setTitle();
            this.updateButtons();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.paypalInfo.modalTitle'));
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },
    },
});
