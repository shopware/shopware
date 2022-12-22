import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import type { PropType } from 'vue';
import type EntityCollection from '@shopware-ag/admin-extension-sdk/es/data/_internals/EntityCollection';
import template from './sw-order-customer-address-select.html.twig';
import './sw-order-customer-address-select.scss';
import type CriteriaType from '../../../../core/data/criteria.data';
import type Repository from '../../../../core/data/repository.data';

/**
 * @package customer-order
 */

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
            type: Object as PropType<Entity<'customer'>>,
            required: true,
        },

        value: {
            type: String as PropType<string>,
            required: true,
        },

        sameAddressLabel: {
            type: String as PropType<string>,
            required: false,
            default: '',
        },

        sameAddressValue: {
            type: String as PropType<string>,
            required: true,
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data(): {
        customerAddresses: EntityCollection<'customer_address'>|[],
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

        addressRepository(): Repository<'customer_address'> {
            return this.repositoryFactory.create(
                (this.customer.addresses?.entity) ?? 'customer_address',
                this.customer.addresses?.source,
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

        getSelectionLabel(item: Entity<'customer_address'>): string {
            if (this.isSameAddress && this.sameAddressLabel) {
                return this.sameAddressLabel;
            }

            return this.getCustomerAddress(item);
        },

        getCustomerAddress(address: Entity<'customer_address'>): string {
            if (!address) return '';

            const result: Array<string> = [];

            const properties = [
                'street',
                'zipcode',
                'city',
                'countryState',
                'country',
            ] as const;

            properties.forEach((property) => {
                const adressProperty = address[property];

                if (!adressProperty) {
                    return;
                }

                if (property === 'countryState' || property === 'country') {
                    const name = address[property]?.translated?.name;

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
                .then((addresses: EntityCollection<'customer_address'>): void => {
                    this.customerAddresses = addresses;
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
                        // @ts-expect-error - hidden does not exist on address entity
                        address.hidden = !addresses.has(address.id);
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
