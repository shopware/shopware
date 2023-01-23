import template from './sw-first-run-wizard-modal.html.twig';
import './sw-first-run-wizard-modal.scss';

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
            title: 'No title defined',
            buttonConfig: [],
            showLoader: false,
            wasNewExtensionActivated: false,
            stepVariant: 'info',
            currentStep: {
                name: '',
                variant: 'large',
                navigationIndex: 0,
            },
            stepper: {
                welcome: {
                    name: 'sw.first.run.wizard.index.welcome',
                    variant: 'large',
                    navigationIndex: 0,
                },
                'data-import': {
                    name: 'sw.first.run.wizard.index.data-import',
                    variant: 'large',
                    navigationIndex: 1,
                },
                defaults: {
                    name: 'sw.first.run.wizard.index.defaults',
                    variant: 'large',
                    navigationIndex: 2,
                },
                'mailer.selection': {
                    name: 'sw.first.run.wizard.index.mailer.selection',
                    variant: 'large',
                    navigationIndex: 3,
                },
                'mailer.smtp': {
                    name: 'sw.first.run.wizard.index.mailer.setup',
                    variant: 'large',
                    navigationIndex: 3,
                },
                'paypal.info': {
                    name: 'sw.first.run.wizard.index.paypal.info',
                    variant: 'large',
                    navigationIndex: 4,
                },
                'paypal.credentials': {
                    name: 'sw.first.run.wizard.index.paypal.credentials',
                    variant: 'large',
                    navigationIndex: 4,
                },
                plugins: {
                    name: 'sw.first.run.wizard.index.plugins',
                    variant: 'large',
                    navigationIndex: 5,
                },
                'shopware.account': {
                    name: 'sw.first.run.wizard.index.shopware.account',
                    variant: 'large',
                    navigationIndex: 6,
                },
                'shopware.domain': {
                    name: 'sw.first.run.wizard.index.shopware.domain',
                    variant: 'large',
                    navigationIndex: 6,
                },
                store: {
                    name: 'sw.first.run.wizard.index.store',
                    variant: 'large',
                    navigationIndex: 7,
                },
                finish: {
                    name: 'sw.first.run.wizard.index.finish',
                    variant: 'large',
                    navigationIndex: 8,
                },
            },
        };
    },

    metaInfo() {
        return {
            title: this.title,
        };
    },

    computed: {
        columns() {
            const res = this.showSteps
                ? '1fr 4fr'
                : '1fr';

            return res;
        },

        variant() {
            const { variant } = this.currentStep;

            return variant;
        },

        showSteps() {
            const { navigationIndex } = this.currentStep;

            return navigationIndex !== 0;
        },

        buttons() {
            return {
                right: this.buttonConfig.filter((button) => button.position === 'right'),
                left: this.buttonConfig.filter((button) => button.position === 'left'),
            };
        },

        stepIndex() {
            const { navigationIndex } = this.currentStep;

            if (navigationIndex < 1) {
                return 0;
            }

            return navigationIndex - 1;
        },

        stepInitialItemVariants() {
            const { navigationIndex } = this.currentStep;
            const maxIndex = Object.values(this.stepper).reduce(
                (accumulator, step) => Math.max(accumulator, step.navigationIndex),
                0,
            );

            const currentSteps = Array(maxIndex + 1).fill('disabled');
            currentSteps.every((step, index) => {
                if (index > navigationIndex) {
                    return false;
                }

                currentSteps[index] = 'info';

                if (index > 0) {
                    currentSteps[index - 1] = 'success';
                }

                return true;
            });
            currentSteps.splice(0, 1);

            return currentSteps;
        },

        isClosable() {
            return !Shopware.Context.app.firstRunWizard;
        },
    },

    watch: {
        '$route'(to) {
            const toName = to.name.replace('sw.first.run.wizard.index.', '');

            this.currentStep = this.stepper[toName];
        },
    },

    mounted() {
        const step = this.$route.name.replace('sw.first.run.wizard.index.', '');

        this.currentStep = this.stepper[step];
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.firstRunWizardService.setFRWStart();
        },

        updateButtons(buttonConfig) {
            this.buttonConfig = buttonConfig;
        },

        onButtonClick(action) {
            if (typeof action === 'string') {
                this.redirect(action);
                return;
            }

            if (typeof action !== 'function') {
                return;
            }

            action.call();
        },

        redirect(routeName) {
            this.$router.push({ name: routeName });
        },

        setTitle(title) {
            this.title = title;
        },

        finishFRW() {
            this.firstRunWizardService.setFRWFinish()
                .then(() => {
                    document.location.href = document.location.origin + document.location.pathname;
                });
        },

        onExtensionActivated() {
            this.wasNewExtensionActivated = true;
        },

        async closeModal() {
            if (!this.isClosable) {
                return;
            }

            this.showLoader = true;

            await this.$nextTick();

            await this.$router.push({ name: 'sw.settings.index.system' });

            // reload page when new extension was activated and modal is closed
            if (this.wasNewExtensionActivated) {
                window.location.reload();
            }
        },
    },
};
