import template from './sw-settings-tax-detail.html.twig';

const { Component, Mixin } = Shopware;
const { mapApiErrors } = Shopware.Component.getComponentHelper();


Component.register('sw-settings-tax-detail', {
    template,

    inject: ['repositoryFactory', 'apiContext'],

    mixins: [
        Mixin.getByName('notification')
    ],

    props: {
        taxId: {
            type: String,
            required: false,
            default: null
        }
    },

    data() {
        return {
            tax: {},
            isLoading: false,
            isSaveSuccessful: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.tax.name || '';
        },

        taxRepository() {
            return this.repositoryFactory.create('tax');
        },

        ...mapApiErrors('tax', ['name', 'taxRate'])
    },

    watch: {
        taxId() {
            if (!this.taxId) {
                this.createdComponent();
            }
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.isLoading = true;
            if (this.taxId) {
                this.taxId = this.$route.params.id;
                this.taxRepository.get(this.taxId, this.apiContext).then((tax) => {
                    this.tax = tax;
                    this.isLoading = false;
                });
                return;
            }

            this.tax = this.taxRepository.create(this.apiContext);
            this.isLoading = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            return this.taxRepository.save(this.tax, this.apiContext).then(() => {
                this.isSaveSuccessful = true;
                if (!this.taxId) {
                    this.$router.push({ name: 'sw.settings.tax.detail', params: { id: this.tax.id } });
                }

                this.taxRepository.get(this.tax.id, this.apiContext).then((updatedTax) => {
                    this.tax = updatedTax;
                    this.isLoading = false;
                });
            }).catch(() => {
                this.createNotificationError({
                    title: this.$tc('sw-settings-tax.detail.notificationErrorTitle'),
                    message: this.$tc('sw-settings-tax.detail.notificationErrorMessage')
                });
                this.isLoading = false;
            });
        }
    }
});
