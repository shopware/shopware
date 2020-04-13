import template from './sw-sales-channel-google-introduction.html.twig';
import './sw-sales-channel-google-introduction.scss';

const { Component } = Shopware;

const gauthOption = {
    // TODO: Import clientId from .env
    clientId: '102783034147-mdukme083o5tbr5aodt9flffcvcm09oq.apps.googleusercontent.com',
    scope: 'profile email https://www.googleapis.com/auth/content',
    prompt: 'consent'
};

Component.register('sw-sales-channel-google-introduction', {
    template,

    inject: ['googleAuthService'],

    props: {
        salesChannel: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    watch: {
        isLoading: {
            handler: 'updateButtons'
        },

        isSaveSuccessful: {
            handler: 'updateButtons'
        }
    },

    created() {
        this.createdComponent();
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        createdComponent() {
            this.updateButtons();
        },

        mountedComponent() {
            this.googleAuthService.load(gauthOption);
        },

        updateButtons() {
            const buttonConfig = {
                right: {
                    label: this.$tc('sw-sales-channel.modalGooglePrograms.step-1.buttonConnect'),
                    variant: 'primary',
                    action: this.onClickConnect,
                    disabled: this.isLoading,
                    isLoading: this.isLoading,
                    isSaveSuccessful: this.isSaveSuccessful,
                    processFinish: this.saveFinish
                },
                left: {
                    label: this.$tc('global.default.cancel'),
                    variant: null,
                    action: this.onCloseModal,
                    disabled: this.isLoading
                }
            };

            this.$emit('buttons-update', buttonConfig);
        },

        onCloseModal() {
            this.$emit('modal-close');
        },

        async onClickConnect() {
            this.isLoading = true;
            this.isSaveSuccessful = false;

            try {
                await this.googleAuthService.getAuthCode(this.connectGoogleAccount);
            } catch (error) {
                // TODO: Implement showing error in another ticket
                console.error('google auth api', error);
            } finally {
                this.isLoading = false;
            }
        },

        async connectGoogleAccount() {
            // TODO: Integrate API service in another ticket
            this.isSaveSuccessful = true;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.sales.channel.detail.base.step-2' });
        }
    }
});
