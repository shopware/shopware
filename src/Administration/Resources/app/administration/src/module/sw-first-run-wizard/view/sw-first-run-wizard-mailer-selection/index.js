import template from './sw-first-run-wizard-mailer-selection.html.twig';
import './sw-first-run-wizard-mailer-selection.scss';

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['systemConfigApiService'],

    data() {
        return {
            mailAgent: null,
            isLoading: false,
        };
    },

    computed: {
        nextLabel() {
            return this.$tc('sw-first-run-wizard.general.buttonNext');
        },

        buttonConfig() {
            return [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.defaults',
                    disabled: false,
                },
                {
                    key: 'configure-later',
                    label: this.$tc('sw-first-run-wizard.general.buttonConfigureLater'),
                    position: 'right',
                    variant: null,
                    action: 'sw.first.run.wizard.index.paypal.info',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.nextLabel,
                    position: 'right',
                    variant: 'primary',
                    action: this.handleSelection.bind(this),
                    disabled: !this.mailAgent,
                },
            ];
        },
    },

    watch: {
        buttonConfig() {
            this.updateButtons();
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

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.mailerSelection.modalTitle'));
        },

        async handleSelection() {
            this.isLoading = true;

            // when user has smtp selected
            if (this.mailAgent === 'smtp') {
                this.$emit('frw-redirect', 'sw.first.run.wizard.index.mailer.smtp');
                this.isLoading = false;
            }

            // when user has local selected
            if (this.mailAgent === 'local') {
                this.$emit('frw-redirect', 'sw.first.run.wizard.index.mailer.local');
                this.isLoading = false;
            }
        },

        setMailAgent(name) {
            this.mailAgent = name;
        },
    },
};
