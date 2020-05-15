import template from './sw-sales-channel-google-programs-modal.html.twig';
import './sw-sales-channel-google-programs-modal.scss';

const { Component, Utils } = Shopware;
const { mapGetters } = Component.getComponentHelper();

Component.register('sw-sales-channel-google-programs-modal', {
    template,

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            buttonConfig: {},
            currentStep: {
                name: '',
                navigationIndex: 0
            },
            stepper: {
                'step-1': {
                    name: 'sw.sales.channel.detail.base.step-1',
                    navigationIndex: 0
                },
                'step-2': {
                    name: 'sw.sales.channel.detail.base.step-2',
                    navigationIndex: 1
                },
                'step-3': {
                    name: 'sw.sales.channel.detail.base.step-3',
                    navigationIndex: 2
                },
                'step-4': {
                    name: 'sw.sales.channel.detail.base.step-4',
                    navigationIndex: 3
                },
                'step-5': {
                    name: 'sw.sales.channel.detail.base.step-5',
                    navigationIndex: 4
                },
                'step-6': {
                    name: 'sw.sales.channel.detail.base.step-6',
                    navigationIndex: 5
                },
                'step-7': {
                    name: 'sw.sales.channel.detail.base.step-7',
                    navigationIndex: 6
                }
            }
        };
    },

    computed: {
        buttonLeft() {
            return Utils.get(this.buttonConfig, 'left');
        },

        buttonRight() {
            return Utils.get(this.buttonConfig, 'right');
        },

        ...mapGetters('swSalesChannel', [
            'needToCompleteTheSetup'
        ])
    },

    watch: {
        '$route'(to) {
            const toName = to.name.replace('sw.sales.channel.detail.base.', '');
            this.checkStep(toName);
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            const step = this.$route.name.replace('sw.sales.channel.detail.base.', '');
            this.checkStep(step);
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

        onCloseModal() {
            this.$emit('modal-close');
        },

        getActiveStyle(item) {
            return {
                'is--active': item.navigationIndex === this.currentStep.navigationIndex
            };
        },

        getCorrectStep(step) {
            if (step === 'sw.sales.channel.detail.base') {
                return this.needToCompleteTheSetup || 'step-1';
            }

            if (this.needToCompleteTheSetup) {
                if (this.stepper[step].navigationIndex <= this.stepper[this.needToCompleteTheSetup].navigationIndex) {
                    return step;
                }
                return this.needToCompleteTheSetup;
            }

            return step;
        },

        checkStep(step) {
            const validStep = this.getCorrectStep(step);
            this.currentStep = this.stepper[validStep];

            if (step !== validStep) {
                this.redirect(`sw.sales.channel.detail.base.${validStep}`);
            }
        }
    }
});

