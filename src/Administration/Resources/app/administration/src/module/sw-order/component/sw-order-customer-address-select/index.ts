import type { PropType } from 'vue';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import template from './sw-order-customer-address-select.html.twig';
import './sw-order-customer-address-select.scss';
import type CriteriaType from '../../../../core/data/criteria.data';
import type { Customer, CustomerAddress } from '../../order.types';
import type Repository from '../../../../core/data/repository.data';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default Component.wrapComponentConfig({
    template,

    inject: ['repositoryFactory'],


    model: {
        prop: 'value',
        event: 'change',
    },

    props: {
        customer: {
            type: Object as PropType<Customer>,
            required: true,
        },

        value: {
            type: String as PropType<string>,
            required: true,
        },

        sameAddressLabel: {
            type: String as PropType<string>,
            required: true,
        },

        sameAddressValue: {
            type: String as PropType<string>,
            required: true,
        },
    },

    data(): {
        customerAddresses: Array<CustomerAddress>,
        isLoading: boolean,
        addressSearchTerm: string,
        } {
        return {
            customerAddresses: [],
            isLoading: false,
            addressSearchTerm: '',
        };
    },

    computed: {
        addressId: {
            get(): string {
                return this.value;
            },
            set(newValue: string|null): void {
                if (newValue === null) {
                    return;
                }

                this.$emit('change', newValue);
            },
        },

        isSameAddress(): boolean {
            return this.value === this.sameAddressValue;
        },

        addressRepository(): Repository {
            return this.repositoryFactory.create(
                this.customer.addresses.entity,
                this.customer.addresses.source,
            );
        },

        addressCriteria(): CriteriaType {
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('salutation');
            criteria.addAssociation('country');
            criteria.addAssociation('countryState');

            if (this.addressSearchTerm) {
                criteria.setTerm(this.addressSearchTerm);
            }

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            void this.getCustomerAddresses();
        },

        getCustomerAddress(address: CustomerAddress): string {
            if (!address) return '';

            const result: Array<string> = [];

            const properties = ['street', 'zipcode', 'city', 'countryState', 'country'] as Array<keyof CustomerAddress>;

            properties.forEach((property: keyof CustomerAddress) => {
                if (!address[property]) return;

                if (property === 'countryState' || property === 'country') {
                    const name = address[property]?.translated.name;

                    if (name) {
                        result.push(name);
                    }

                    return;
                }

                result.push(<string> address[property]);
            });

            return result.join(', ');
        },

        getCustomerAddresses(): Promise<void> {
            this.isLoading = true;

            // Get the latest addresses from customer's db
            return this.addressRepository.search(this.addressCriteria)
                .then((addresses: EntityCollection): void => {
                    this.customerAddresses = addresses as unknown as Array<CustomerAddress>;
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        searchAddress(searchTerm: string): Promise<void> {
            this.isLoading = true;

            this.addressSearchTerm = searchTerm;

            return this.addressRepository.search(this.addressCriteria)
                .then((addresses) => {
                    this.customerAddresses.forEach((address) => {
                        address.hidden = !addresses.has(address.id);
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        setSameAddress(): void {
            this.$emit('change', this.sameAddressValue);
        },
    },
});
