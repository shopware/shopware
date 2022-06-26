import template from './sw-settings-country-address-handling.html.twig';
import './sw-settings-country-address-handling.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const utils = Shopware.Utils;
const { cloneDeep } = utils.object;

Component.register('sw-settings-country-address-handling', {
    template,

    inject: [
        'acl',
    ],

    props: {
        country: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data() {
        return {
            advancedPostalCodePattern: null,
            draggedItem: null,
            droppedItem: null,
            advancedAddressFormat: null,
            advancedAddressFormatClone: null,
            snippets: null,
            customerId: null,
            customer: null,
            isOpenModal: false,
            currentPosition: null,
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

        dragConf() {
            return {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                onDragStart: this.dragStart,
                onDragEnter: this.onMouseEnter,
                onDrop: this.dragEnd,
                preventEvent: false,
            };
        },

        previewData() {
            return {
                customer: this.customer,
            };
        },

        isDisabled() {
            return this.advancedAddressFormat?.length <= 1;
        },
    },

    watch: {
        'country.checkPostalCodePattern'(value) {
            if (value) {
                return;
            }

            this.$set(this.country, 'checkAdvancedPostalCodePattern', false);
        },

        'country.checkAdvancedPostalCodePattern'(value) {
            if (value) {
                if (this.country.advancedPostalCodePattern && !this.advancedPostalCodePattern) {
                    return;
                }

                this.$set(this.country, 'advancedPostalCodePattern', this.advancedPostalCodePattern);
                return;
            }

            this.advancedPostalCodePattern = this.country.advancedPostalCodePattern;
            this.$set(this.country, 'advancedPostalCodePattern', null);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.advancedAddressFormatClone = cloneDeep(this.advancedAddressFormat);
            this.advancedPostalCodePattern = this.country.advancedPostalCodePattern;

            this.getAdvancedAddressFormat();
            this.getSnippets();
        },

        dragStart(config) {
            this.draggedItem = config.data;
        },

        onMouseEnter(dragData, dropData) {
            if (!this.draggedItem) {
                return;
            }

            if (!dragData || !dropData) {
                return;
            }

            this.droppedItem = dropData;
        },

        dragEnd() {
            if (![this.draggedItem?.index, this.droppedItem?.index]
                .every(position => typeof position === 'number')
            ) {
                return;
            }

            this.advancedAddressFormat = Object.assign(
                [],
                this.advancedAddressFormat,
                {
                    [this.draggedItem.index]: this.advancedAddressFormat[this.droppedItem.index],
                    [this.droppedItem.index]: this.advancedAddressFormat[this.draggedItem.index],
                },
            );

            this.draggedItem = null;
            this.droppedItem = null;
        },

        onDragEnd(dragPosition, { dragData, dropData }) {
            if (![dragPosition, dropData?.index].every(position => typeof position === 'number')) {
                return;
            }

            // swap positions in the same line
            if (dropData.linePosition === dragData.linePosition) {
                const newValue = Object.assign(
                    [],
                    this.advancedAddressFormat[dragPosition],
                    {
                        [dragData.index]: this.advancedAddressFormat[dragPosition][dropData.index],
                        [dropData.index]: this.advancedAddressFormat[dragPosition][dragData.index],
                    },
                );

                this.$set(this.advancedAddressFormat, dragPosition, newValue);
                return;
            }

            // swap positions in different lines
            if (typeof dropData?.linePosition === 'number' && dragData.linePosition !== dropData.linePosition) {
                this.$set(this.advancedAddressFormat[dragData.linePosition], dragData.index, dropData.snippet);
                this.$set(this.advancedAddressFormat[dropData.linePosition], dropData.index, dragData.snippet);
                return;
            }

            // move to another line
            this.$set(
                this.advancedAddressFormat,
                `${dropData.index}`,
                [...this.advancedAddressFormat[dropData.index], dragData.snippet],
            );

            this.advancedAddressFormat[dragPosition].splice(dragData.index, 1);
            this.$set(
                this.advancedAddressFormat,
                dragPosition,
                this.advancedAddressFormat[dragPosition],
            );
        },

        moveToLocation(source, dest) {
            dest = typeof dest !== 'number' ? this.advancedAddressFormat.length - 1 : dest;
            const snippet = this.advancedAddressFormat[source];

            this.advancedAddressFormat = this.swapPosition(source, dest, [snippet]);
        },

        addNewLineAt(source, dest) {
            const snippet = this.advancedAddressFormat[source];
            const swag = dest === 'above' ? [[], snippet] : [snippet, []];

            this.advancedAddressFormat = this.swapPosition(source, source, swag);
        },

        swapPosition(source, dest, swag) {
            const newSnippets = [
                ...this.advancedAddressFormat.filter((_, key) => key !== source),
            ];

            newSnippets.splice(dest, 0, ...swag);

            return newSnippets;
        },

        change(index, newSnippet, isDelete = false) {
            if (isDelete) {
                this.advancedAddressFormat = this.advancedAddressFormat.filter((_, key) => index !== key);
                return;
            }

            this.$set(this.advancedAddressFormat, index, newSnippet);
        },

        customerLabel(item) {
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

            this.customer = customer;
        },

        resetMarkup() {
            this.advancedAddressFormat = cloneDeep(this.advancedAddressFormatClone);
        },

        openSnippetModal(position) {
            this.isOpenModal = true;
            this.currentPosition = position;
        },

        onCloseModal() {
            this.currentPosition = null;
            this.isOpenModal = false;
        },

        getSnippets() {
            // todo call API and get results

            // mock data
            this.snippets = [
                {
                    id: 'customer.defaultBillingAddress.company',
                    name: 'Company name',
                    parentId: 'customer.defaultBillingAddress',
                    schema: 'customer.defaultBillingAddress.company',
                },
                {
                    id: 'customer.defaultBillingAddress.department',
                    name: 'Department',
                    parentId: 'customer.defaultBillingAddress',
                    schema: 'customer.defaultBillingAddress.department',
                },
                {
                    id: 'customer.firstName',
                    name: 'First name',
                    parentId: 'customer',
                    schema: 'customer.firstName',
                },
                {
                    id: 'customer.lastName',
                    name: 'Last name',
                    parentId: 'customer',
                    schema: 'customer.lastName',
                },
                {
                    id: 'customer.defaultBillingAddress.street',
                    name: 'Street name',
                    parentId: 'customer.defaultBillingAddress',
                    schema: 'customer.defaultBillingAddress.street',
                },
                {
                    id: 'customer.defaultBillingAddress.zipcode',
                    name: 'Zip code',
                    parentId: 'customer.defaultBillingAddress',
                    schema: 'customer.defaultBillingAddress.zipcode',
                },
                {
                    id: 'customer.defaultBillingAddress.city',
                    name: 'City',
                    parentId: 'customer.defaultBillingAddress',
                    schema: 'customer.defaultBillingAddress.city',
                },
                {
                    id: 'customer.defaultBillingAddress.country.name',
                    name: 'Country',
                    parentId: 'customer.defaultBillingAddress.country',
                    schema: 'customer.defaultBillingAddress.country.name',
                },
                {
                    id: 'customer.defaultBillingAddress.title',
                    name: 'Title',
                    parentId: 'customer.defaultBillingAddress.title',
                    schema: 'customer.defaultBillingAddress.title',
                },
                {
                    id: 'customer.defaultBillingAddress.salutation.displayName',
                    name: 'Salutation name',
                    parentId: 'customer.defaultBillingAddress.salutation.displayName',
                    schema: 'customer.defaultBillingAddress.salutation.displayName',
                },
            ];
        },

        getAdvancedAddressFormat() {
            // todo reprocess the returned data

            // mock data
            this.advancedAddressFormat = [
                [
                    { value: 'customer.defaultBillingAddress.company', label: 'Company name' },
                    { value: 'snippet.custom', label: '-' },
                    { value: 'customer.defaultBillingAddress.department', label: 'Department' },
                ],
                [{ value: 'customer.firstName', label: 'First name' }, { value: 'customer.lastName', label: 'Last name' }],
                [{ value: 'customer.defaultBillingAddress.street', label: 'Street name' }],
                [
                    { value: 'customer.defaultBillingAddress.zipcode', label: 'Zip code' },
                    { value: 'customer.defaultBillingAddress.city', label: 'City' },
                ],
                [{ value: 'customer.defaultBillingAddress.country.name', label: 'Country' }],
            ];

            this.advancedAddressFormatClone = cloneDeep(this.advancedAddressFormat);
        },
    },
});
