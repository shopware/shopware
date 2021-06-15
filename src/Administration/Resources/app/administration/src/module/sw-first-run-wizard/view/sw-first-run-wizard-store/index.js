import template from './sw-first-run-wizard-store.html.twig';
import './sw-first-run-wizard-store.scss';

const { Component } = Shopware;

Component.register('sw-first-run-wizard-store', {
    template,

    inject: ['extensionHelperService'],

    data() {
        return {
            loadStatus: false,
            isActivating: false,
            activationError: null,
            extensionStatus: null,
            error: null,
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

        buttonConfig() {
            const backButton = {
                key: 'back',
                label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                position: 'left',
                variant: null,
                action: 'sw.first.run.wizard.index.shopware.account',
                disabled: this.isActivating || this.loadStatus,
            };

            if (this.extensionStatus && this.extensionStatus.active) {
                return [
                    backButton,
                    {
                        key: 'next',
                        label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                        position: 'right',
                        variant: 'primary',
                        action: 'sw.first.run.wizard.index.finish',
                        disabled: false,
                    },
                ];
            }

            return [
                backButton,
                {
                    key: 'skip',
                    label: this.$tc('sw-first-run-wizard.general.buttonSkip'),
                    position: 'right',
                    variant: null,
                    action: 'sw.first.run.wizard.index.finish',
                    disabled: this.isActivating || this.loadStatus,
                },
                {
                    key: 'activate',
                    label: this.$tc('sw-first-run-wizard.general.buttonActivate'),
                    position: 'right',
                    variant: 'primary',
                    action: this.activateStore.bind(this),
                    disabled: this.isActivating || this.loadStatus,
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
            this.updateExtensionStatus();
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.store.modalTitle'));
        },

        async updateExtensionStatus() {
            this.loadStatus = true;

            try {
                this.extensionStatus = await this.extensionHelperService.getStatusOfExtension('SwagExtensionStore');
            } catch (error) {
                Shopware.Utils.debug.error(error);
            } finally {
                this.loadStatus = false;
            }
        },

        activateStore() {
            this.isActivating = true;
            this.activationError = null;

            Promise.all([this.installExtensionStore()])
                .then(() => {
                    this.$emit('frw-redirect', 'sw.first.run.wizard.index.finish');
                })
                .catch((error) => {
                    this.activationError = true;

                    if (error?.response?.data &&
                        Array.isArray(error.response.data.errors) &&
                        error.response.data.errors[0]
                    ) {
                        this.error = error.response.data.errors[0];
                    }

                    Shopware.Utils.debug.error(error);
                })
                .finally(() => {
                    this.isActivating = false;
                });
        },

        installExtensionStore() {
            return this.extensionHelperService.downloadAndActivateExtension('SwagExtensionStore');
        },

        updateButtons() {
            this.$emit('buttons-update', this.buttonConfig);
        },
    },
});
