import template from './sw-customer-detail.html.twig';
import errorConfig from './error-config.json';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { mapPageErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-customer-detail', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('salutation'),
        Mixin.getByName('discard-detail-page-changes')('customer')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onAbortButtonClick'
    },

    props: {
        customerId: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            customer: null,
            customerAddressCustomFieldSets: [],
            customerCustomFieldSets: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.customer !== null ? this.salutation(this.customer) : '';
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        editMode: {
            get() {
                if (typeof this.$route.query.edit === 'boolean') {
                    return this.$route.query.edit;
                }

                return this.$route.query.edit === 'true';
            },
            set(editMode) {
                this.$router.push({ name: this.$route.name, query: { edit: editMode } });
            }
        },

        defaultCriteria() {
            const criteria = new Criteria();
            criteria
                .addAssociation('addresses')
                .addAssociation('group')
                .addAssociation('salutation')
                .addAssociation('salesChannel')
                .addAssociation('defaultPaymentMethod')
                .addAssociation('lastPaymentMethod')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation')
                .addAssociation('defaultShippingAddress.country')
                .addAssociation('defaultShippingAddress.countryState')
                .addAssociation('defaultShippingAddress.salutation')
                .addAssociation('tags');

            return criteria;
        },

        generalRoute() {
            return {
                name: 'sw.customer.detail.base',
                params: { id: this.customerId },
                query: { edit: this.editMode }
            };
        },

        addressesRoute() {
            return {
                name: 'sw.customer.detail.addresses',
                params: { id: this.customerId },
                query: { edit: this.editMode }
            };
        },

        ordersRoute() {
            return {
                name: 'sw.customer.detail.order',
                params: { id: this.customerId },
                query: { edit: this.editMode }
            };
        },

        ...mapPageErrors(errorConfig)
    },

    created() {
        this.createdComponent();
    },

    watch: {
        customerId() {
            this.createdComponent();
        }
    },

    methods: {
        createdComponent() {
            this.isLoading = true;

            this.customerRepository.get(
                this.customerId,
                Shopware.Context.api,
                this.defaultCriteria
            ).then((customer) => {
                this.customer = customer;
                this.isLoading = false;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.editMode = false;
        },

        onSave() {
            if (!this.editMode) {
                return false;
            }

            this.isLoading = true;
            this.isSaveSuccessful = false;

            if (!this.customer.birthday) {
                this.customer.birthday = null;
            }

            return this.customerRepository.save(this.customer, Shopware.Context.api).then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
                this.createdComponent();
            }).catch((exception) => {
                this.createNotificationError({
                    title: this.$tc('sw-customer.detail.titleSaveError'),
                    message: this.$tc('sw-customer.detail.messageSaveError')
                });
                this.isLoading = false;
                throw exception;
            });
        },

        onAbortButtonClick() {
            this.discardChanges();
            this.editMode = false;
        },

        onActivateCustomerEditMode() {
            this.editMode = true;
        }
    }
});
