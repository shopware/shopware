import template from './sw-first-run-wizard-defaults.html.twig';
import './sw-first-run-wizard-defaults.scss';

/**
 * @package checkout
 * @private
 */
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory'],

    emits: [
        'frw-set-title',
        'frw-redirect',
        'buttons-update',
    ],

    data() {
        return {
            isLoading: false,
            defaultSalesChannelCardLoaded: false,
            salesChannel: null,
            configData: {
                null: {
                    'core.defaultSalesChannel.salesChannel': [],
                    'core.defaultSalesChannel.active': true,
                    'core.defaultSalesChannel.visibility': {},
                },
            },
        };
    },

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        buttonConfig() {
            const buttons = [
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.nextAction.bind(this),
                    disabled: !this.defaultSalesChannelCardLoaded,
                },
            ];

            if (!Shopware.State.get('context').app.config.settings.disableExtensionManagement) {
                buttons.unshift({
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.data-import',
                    disabled: false,
                });
            }

            return buttons;
        },
    },

    watch: {
        buttonConfig: {
            handler() {
                this.updateButtons();
            },
            deep: true,
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
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.defaults.modalTitle'));
        },

        async nextAction() {
            this.isLoading = true;

            await this.$refs.defaultSalesChannelCard.saveSalesChannelVisibilityConfig();

            this.isLoading = false;
            this.$emit('frw-redirect', 'sw.first.run.wizard.index.mailer.selection');
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        updateSalesChannel(salesChannel) {
            this.salesChannel = salesChannel;
        },
    },
};
