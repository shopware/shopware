import template from './sw-first-run-wizard-markets.html.twig';
import './sw-first-run-wizard-markets.scss';

Shopware.Component.register('sw-first-run-wizard-markets', {
    template,

    inject: ['extensionHelperService'],

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
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: 'sw.first.run.wizard.index.plugins',
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
            this.updateButtons();
            this.setTitle();
            this.installMarkets();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.markets.modalTitle'));
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        installMarkets() {
            Promise.all([this.extensionHelperService.downloadAndActivateExtension('SwagMarkets')])
                .catch((error) => {
                    Shopware.Utils.debug.error(error);
                })
                .finally(() => {
                    this.isInstalling = false;
                });
        },
    },
});
