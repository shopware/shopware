import template from './sw-first-run-wizard-shopware-domain.html.twig';
import './sw-first-run-wizard-shopware-domain.scss';

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['firstRunWizardService'],

    emits: [
        'frw-set-title',
        'buttons-update',
        'frw-redirect',
    ],

    data() {
        return {
            licenceDomains: [],
            selectedShopDomain: '',
            createShopDomain: false,
            newShopDomain: '',
            testEnvironment: false,
            domainError: null,
            isLoading: false,
        };
    },

    computed: {
        domainToVerify() {
            return this.createShopDomain ? this.newShopDomain : this.selectedShopDomain;
        },

        isDomainEmpty() {
            return this.domainToVerify.length <= 0;
        },

        nextAction() {
            if (Shopware.State.get('context').app.config.settings.disableExtensionManagement) {
                return 'sw.first.run.wizard.index.finish';
            }

            return 'sw.first.run.wizard.index.store';
        },
    },

    watch: {
        isDomainEmpty() {
            this.updateButtons();
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            this.updateButtons();
            this.setTitle();

            this.firstRunWizardService
                .getLicenseDomains()
                .then((response) => {
                    const { items } = response;

                    if (!items || items.length < 1) {
                        return;
                    }

                    this.licenceDomains = items;
                    this.selectedShopDomain = items[0].domain;
                })
                .finally(() => {
                    if (this.licenceDomains.length <= 0) {
                        this.createShopDomain = true;
                    }
                    this.isLoading = false;
                });
        },

        setTitle() {
            this.$emit('frw-set-title', this.$tc('sw-first-run-wizard.shopwareAccount.modalTitle'));
        },

        updateButtons() {
            const buttonConfig = [
                {
                    key: 'back',
                    label: this.$tc('sw-first-run-wizard.general.buttonBack'),
                    position: 'left',
                    variant: null,
                    action: 'sw.first.run.wizard.index.shopware.account',
                    disabled: false,
                },
                {
                    key: 'next',
                    label: this.$tc('sw-first-run-wizard.general.buttonNext'),
                    position: 'right',
                    variant: 'primary',
                    action: this.verifyDomain.bind(this),
                    disabled: this.isDomainEmpty,
                },
            ];

            this.$emit('buttons-update', buttonConfig);
        },

        verifyDomain() {
            const { testEnvironment } = this;
            const domain = this.domainToVerify;

            this.domainError = null;

            return this.firstRunWizardService
                .verifyLicenseDomain({
                    domain,
                    testEnvironment,
                })
                .then(() => {
                    this.$emit('frw-redirect', this.nextAction);
                    return false;
                })
                .catch((error) => {
                    const msg = error.response.data.errors.pop();

                    this.domainError = msg;

                    return true;
                });
        },
    },
};
