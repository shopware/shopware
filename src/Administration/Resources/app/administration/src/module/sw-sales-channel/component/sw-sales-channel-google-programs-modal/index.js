import template from './sw-sales-channel-google-programs-modal.html.twig';
import './sw-sales-channel-google-programs-modal.scss';

const { Component, Utils } = Shopware;

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
        }
    },

    watch: {
        '$route'(to) {
            const toName = to.name.replace('sw.sales.channel.detail.base.', '');
            this.currentStep = this.stepper[toName];
        }
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            if (this.$route.name === 'sw.sales.channel.detail.base') {
                // TODO: Implement handle navigation logic in another MR
                this.currentStep = this.stepper['step-1'];
                this.$router.push({ name: this.stepper['step-1'].name });
            } else {
                const step = this.$route.name.replace('sw.sales.channel.detail.base.', '');
                this.currentStep = this.stepper[step];
            }
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
        }
    }
});

