import template from './sw-first-run-wizard-modal.html.twig';
import './sw-first-run-wizard-modal.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-modal', {
    template,

    inject: ['firstRunWizardService'],

    provide() {
        return {
            addNextCallback: this.addNextCallback
        };
    },

    props: {
        title: {
            type: String,
            required: true,
            default: 'unknown title'
        }
    },

    data() {
        return {
            nextCallback: null,
            stepVariant: 'info',
            currentStep: {
                name: '',
                variant: 'large',
                navigationIndex: 0,
                next: false,
                skip: false,
                back: false,
                finish: false
            },
            stepper: {
                welcome: {
                    name: 'sw.first.run.wizard.index.welcome',
                    variant: 'large',
                    navigationIndex: 0,
                    next: 'sw.first.run.wizard.index.demodata',
                    install: false,
                    skip: false,
                    back: false,
                    finish: false
                },
                demodata: {
                    name: 'sw.first.run.wizard.index.demodata',
                    variant: 'large',
                    navigationIndex: 1,
                    next: false,
                    install: 'sw.first.run.wizard.index.paypal.info',
                    skip: 'sw.first.run.wizard.index.paypal.info',
                    back: false,
                    finish: false
                },
                'paypal.info': {
                    name: 'sw.first.run.wizard.index.paypal.info',
                    variant: 'large',
                    navigationIndex: 2,
                    next: 'sw.first.run.wizard.index.paypal.credentials',
                    install: false,
                    skip: 'sw.first.run.wizard.index.plugins',
                    back: 'sw.first.run.wizard.index.demodata',
                    finish: false
                },
                'paypal.credentials': {
                    name: 'sw.first.run.wizard.index.paypal.credentials',
                    variant: 'large',
                    navigationIndex: 2,
                    next: 'sw.first.run.wizard.index.plugins',
                    install: false,
                    skip: 'sw.first.run.wizard.index.plugins',
                    back: 'sw.first.run.wizard.index.paypal.info',
                    finish: false
                },
                plugins: {
                    name: 'sw.first.run.wizard.index.plugins',
                    variant: 'large',
                    navigationIndex: 3,
                    next: 'sw.first.run.wizard.index.shopware.account',
                    install: false,
                    skip: false,
                    back: 'sw.first.run.wizard.index.paypal.info',
                    finish: false
                },
                'shopware.account': {
                    name: 'sw.first.run.wizard.index.shopware.account',
                    variant: 'large',
                    navigationIndex: 4,
                    next: 'sw.first.run.wizard.index.shopware.domain',
                    install: false,
                    skip: 'sw.first.run.wizard.index.finish',
                    back: 'sw.first.run.wizard.index.plugins',
                    finish: false
                },
                'shopware.domain': {
                    name: 'sw.first.run.wizard.index.shopware.domain',
                    variant: 'large',
                    navigationIndex: 4,
                    next: 'sw.first.run.wizard.index.finish',
                    install: false,
                    skip: false,
                    back: 'sw.first.run.wizard.index.shopware.account',
                    finish: false
                },
                finish: {
                    name: 'sw.first.run.wizard.index.finish',
                    variant: 'large',
                    navigationIndex: 5,
                    next: false,
                    install: false,
                    skip: false,
                    back: 'sw.first.run.wizard.index.shopware.account',
                    finish: true
                }
            }
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

        nextVisible() {
            return !!this.currentStep.next;
        },

        installable() {
            return !!this.currentStep.install;
        },

        backVisible() {
            return !!this.currentStep.back;
        },

        skipable() {
            return !!this.currentStep.skip;
        },

        finishable() {
            return !!this.currentStep.finish;
        },

        stepIndex() {
            const { navigationIndex } = this.currentStep;

            if (navigationIndex < 1) {
                return 0;
            }

            return navigationIndex - 1;
        },

        stepInitialItemVariants() {
            const navigationSteps = [
                ['disabled', 'disabled', 'disabled', 'disabled'],
                ['info', 'disabled', 'disabled', 'disabled'],
                ['success', 'info', 'disabled', 'disabled'],
                ['success', 'success', 'info', 'disabled'],
                ['success', 'success', 'success', 'info'],
                ['success', 'success', 'success', 'success']
            ];
            const { navigationIndex } = this.currentStep;

            return navigationSteps[navigationIndex];
        }
    },

    watch: {
        '$route'(to) {
            const toName = to.name.replace('sw.first.run.wizard.index.', '');

            this.currentStep = this.stepper[toName];
        }
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

        onNext() {
            const { next } = this.currentStep;

            let callbackPromise = Promise.resolve(false);

            if (this.nextCallback !== null && typeof this.nextCallback === 'function') {
                callbackPromise = this.nextCallback.call();
            }

            callbackPromise.then((abort) => {
                if (!abort) {
                    this.redirect(next);
                }
            });
        },

        onInstall() {
            const { install } = this.currentStep;

            let callbackPromise = Promise.resolve(false);

            if (this.nextCallback !== null && typeof this.nextCallback === 'function') {
                callbackPromise = this.nextCallback.call();
            }

            callbackPromise.then((abort) => {
                if (!abort) {
                    this.redirect(install);
                }
            });
        },

        onSkip() {
            const { skip } = this.currentStep;

            this.redirect(skip);
        },

        onBack() {
            const { back } = this.currentStep;

            this.redirect(back);
        },

        onFinish() {
            let callbackPromise = Promise.resolve(false);

            if (this.nextCallback !== null && typeof this.nextCallback === 'function') {
                callbackPromise = this.nextCallback.call();
            }

            callbackPromise.then((abort) => {
                if (!abort) {
                    this.firstRunWizardService.setFRWFinish()
                        .then(() => {
                            document.location.href = document.location.origin + document.location.pathname;
                        });
                }
            });
        },

        redirect(routeName) {
            this.nextCallback = null;
            this.$router.push({ name: routeName });
        },

        addNextCallback(fs) {
            this.nextCallback = fs;
        }
    }
});

