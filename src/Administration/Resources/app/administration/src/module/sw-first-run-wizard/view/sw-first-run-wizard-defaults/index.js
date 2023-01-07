import template from './sw-first-run-wizard-defaults.html.twig';
import './sw-first-run-wizard-defaults.scss';

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

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
            return [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.data-import',
                    disabled: false,
                }, {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.nextAction.bind(this),
                    disabled: !this.defaultSalesChannelCardLoaded,
                },
            ];
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
