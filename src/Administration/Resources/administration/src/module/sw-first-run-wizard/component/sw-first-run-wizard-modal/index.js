import { Component } from 'src/core/shopware';
import template from './sw-first-run-wizard-modal.html.twig';
import './sw-first-run-wizard-modal.scss';

Component.register('sw-first-run-wizard-modal', {
    template,

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
            stepIndex: 0,
            nextCallback: null,
            stepVariant: 'info',
            stepInitialItemVariants: [
                'disabled',
                'disabled',
                'disabled',
                'disabled'
            ],
            currentStep: {
                name: '',
                next: false,
                skip: false,
                back: false,
                finish: false
            },
            stepper: {
                welcome: {
                    name: 'sw.first.run.wizard.index.welcome',
                    next: 'sw.first.run.wizard.index.demodata',
                    skip: false,
                    back: false,
                    finish: false
                },
                demodata: {
                    name: 'sw.first.run.wizard.index.demodata',
                    next: 'sw.first.run.wizard.index.paypal.info',
                    skip: 'sw.first.run.wizard.index.paypal.info',
                    back: false,
                    finish: false
                },
                'paypal.info': {
                    name: 'sw.first.run.wizard.index.paypal.info',
                    next: 'sw.first.run.wizard.index.paypal.install',
                    skip: 'sw.first.run.wizard.index.plugins',
                    back: false,
                    finish: false
                },
                'paypal.install': {
                    name: 'sw.first.run.wizard.index.paypal.install',
                    next: 'sw.first.run.wizard.index.paypal.credentials',
                    skip: false,
                    back: false,
                    finish: false
                },
                'paypal.credentials': {
                    name: 'sw.first.run.wizard.index.paypal.credentials',
                    next: 'sw.first.run.wizard.index.plugins',
                    skip: 'sw.first.run.wizard.index.plugins',
                    back: 'sw.first.run.wizard.index.paypal.info',
                    finish: false
                },
                plugins: {
                    name: 'sw.first.run.wizard.index.plugins',
                    next: 'sw.first.run.wizard.index.shopware.account',
                    skip: false,
                    back: 'sw.first.run.wizard.index.paypal.info',
                    finish: false
                },
                'shopware.account': {
                    name: 'sw.first.run.wizard.index.shopware.account',
                    next: 'sw.first.run.wizard.index.shopware.domain',
                    skip: false,
                    back: 'sw.first.run.wizard.index.paypal.credentials',
                    finish: false
                },
                'shopware.domain': {
                    name: 'sw.first.run.wizard.index.shopware.domain',
                    next: 'sw.first.run.wizard.index.finish',
                    skip: false,
                    back: 'sw.first.run.wizard.index.shopware.account',
                    finish: false
                },
                finish: {
                    name: 'sw.first.run.wizard.index.finish',
                    next: false,
                    skip: false,
                    back: 'sw.first.run.wizard.index.shopware.domain',
                    finish: true
                }
            }
        };
    },

    computed: {
        nextVisible() {
            return !!this.currentStep.next;
        },

        backVisible() {
            return !!this.currentStep.back;
        },

        skipable() {
            return !!this.currentStep.skip;
        },

        finishable() {
            return !!this.currentStep.finish;
        }
    },

    watch: {
        '$route'(to, from) {
            const fromName = from.name.replace('sw.first.run.wizard.index.', '');
            const toName = to.name.replace('sw.first.run.wizard.index.', '');

            console.log({ fromName, toName });
            this.currentStep = this.stepper[toName];
        }
    },

    mounted() {
        const step = this.$route.name.replace('sw.first.run.wizard.index.', '');

        this.currentStep = this.stepper[step];
    },

    methods: {
        onNext() {
            const { next } = this.currentStep;

            let callbackPromise = Promise.resolve(false);

            if (this.nextCallback !== null && typeof this.nextCallback === 'function') {
                callbackPromise = this.nextCallback.call();
            }

            callbackPromise.then(abort => {
                if (!abort) {
                    this.redirect(next);
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

        redirect(routeName) {
            this.nextCallback = null;
            this.$router.push({ name: routeName });
        },

        onFinish() {
            document.location.href = document.location.origin;
        },
        addNextCallback(fs) {
            this.nextCallback = fs;
        }
    }
});

