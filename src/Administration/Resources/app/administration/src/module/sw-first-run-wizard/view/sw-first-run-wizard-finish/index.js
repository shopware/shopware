import template from './sw-first-run-wizard-finish.html.twig';
import './sw-first-run-wizard-finish.scss';

/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['firstRunWizardService'],

    data() {
        return {
            licenceDomains: [],
            licensed: false,
            restarting: false,
        };
    },

    computed: {
        edition() {
            const activeDomain = this.licenceDomains.find((domain) => domain.active);

            if (!activeDomain) {
                return '';
            }

            return activeDomain.edition;
        },

        successMessage() {
            if (!this.licensed) {
                return this.$tc('sw-first-run-wizard.finish.messageNotLicensed');
            }

            const { edition } = this;

            return this.$tc('sw-first-run-wizard.finish.message', {}, { edition });
        },

        buttonConfig() {
            return [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.store',
                    disabled: false,
                },
                {
                    key: 'finish',
                    label: this.$tc('sw-first-run-wizard.general.buttonFinish'),
                    position: 'right',
                    variant: 'primary',
                    action: this.onFinish.bind(this),
                    disabled: false,
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
        this.setTitle();
    },

    methods: {
        createdComponent() {
            this.updateButtons();

            this.firstRunWizardService.getLicenseDomains().then((response) => {
                const { items } = response;

                if (!items || items.length < 1) {
                    return;
                }

                this.licenceDomains = items;
                this.licensed = true;
            }).catch(() => {
                this.licensed = false;
            });
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.finish.modalTitle'));
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },

        onFinish() {
            this.restarting = true;
            this.$emit('frw-finish', true);
        },
    },
};
