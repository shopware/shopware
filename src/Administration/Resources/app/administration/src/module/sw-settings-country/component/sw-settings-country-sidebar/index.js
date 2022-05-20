import { dom } from 'src/core/service/util.service';
import template from './sw-settings-country-sidebar.html.twig';
import './sw-settings-country-sidebar.scss';
import { ADDRESS_VARIABLES } from '../../constant/address.constant';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-country-sidebar', {
    template,

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            variables: ADDRESS_VARIABLES,
            customerId: null,
            previewData: null,
            customer: null,
        };
    },

    computed: {
        customerCriteria() {
            const criteria = new Criteria(1, null);
            criteria
                .addAssociation('salutation')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation');

            return criteria;
        },
    },

    methods: {
        onClickShowPreview() {
            this.$emit('open-preview-modal', this.customer);
        },

        onCopyVariable(variable) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(`{{ ${variable} }}`).catch((error) => {
                    const errorMsg = `${this.$tc('sw-mail-template.detail.textErrorMessage')}: "${error}"`;

                    this.createNotificationError({
                        message: errorMsg,
                    });
                });

                return;
            }

            dom.copyToClipboard(`{{ ${variable} }}`);
        },

        getCustomerLabel(item) {
            if (!item) {
                return '';
            }

            return `${item?.translated?.firstName || item?.firstName}, ${item?.translated?.lastName || item?.lastName}`;
        },

        onChangeCustomer(customerId, customer) {
            this.customer = null;
            if (!customerId || !customer) {
                return;
            }

            const { defaultBillingAddress } = customer;

            if (!defaultBillingAddress) {
                return;
            }

            this.customer = customer;
            this.previewData = {
                company: defaultBillingAddress?.company,
                department: defaultBillingAddress?.department,
                title: customer.title,
                firstName: customer.firstName,
                lastName: customer.lastName,
                street: defaultBillingAddress?.street,
                city: defaultBillingAddress?.city,
                country: defaultBillingAddress?.country?.translated?.name
                    || defaultBillingAddress?.country?.name,
                countryState: defaultBillingAddress?.countryState?.translated?.name
                    || defaultBillingAddress?.countryState?.name,
                salutation: defaultBillingAddress?.salutation?.translated?.displayName
                    || defaultBillingAddress?.salutation?.displayName,
                phoneNumber: defaultBillingAddress?.phoneNumber,
                zipcode: defaultBillingAddress?.zipcode,
                additionalAddressLine1: defaultBillingAddress?.additionalAddressLine1,
                additionalAddressLine2: defaultBillingAddress?.additionalAddressLine2,
            };
        },
    },
});
