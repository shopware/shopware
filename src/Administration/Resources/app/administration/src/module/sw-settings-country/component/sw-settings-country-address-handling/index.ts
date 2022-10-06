import camelCase from 'lodash/camelCase';
import type CriteriaType from 'src/core/data/criteria.data';
import type { Address, Snippet } from 'src/core/service/api/custom-snippet.api.service';
import type { PropType } from 'vue';
import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import template from './sw-settings-country-address-handling.html.twig';
import './sw-settings-country-address-handling.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

interface CustomerEntity extends Entity {
    firstName: string,
    lastName: string,
    defaultBillingAddress: Address
}

interface DragConfig {
    delay: number,
    dragGroup: number | string,
    draggableCls: string,
    draggingStateCls: string,
    dragElementCls: string,
    validDragCls: string,
    invalidDragCls: string,
    preventEvent: boolean,
    validateDrop: boolean,
    validateDrag: boolean,
    onDragStart: (...args: never[]) => void,
    onDragEnter: (...args: never[]) => void,
    onDragLeave: (...args: never[]) => void,
    onDrop: (...args: never[]) => void,
    // eslint-disable-next-line no-use-before-define
    data: DragItem,
    disabled: boolean,
}

interface TreeItem {
    id: string,
    name: string,
    parentId?: string | null,
}

interface DragItem {
    index: number,
    linePosition?: number | null,
    snippet: Snippet[]
}

interface CountryEntity extends Entity {
    forceStateInRegistration: boolean,
    postalCodeRequired: boolean,
    checkPostalCodePattern: boolean,
    checkAdvancedPostalCodePattern: boolean,
    advancedPostalCodePattern: string|null,
    addressFormat: Array<Snippet[]> | []
}

const CUSTOM_SNIPPET_TYPE = {
    PLAIN: 'plain',
    SNIPPET: 'snippet',
} as { PLAIN: string, SNIPPET: string };

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
Component.register('sw-settings-country-address-handling', {
    template,

    inject: [
        'acl',
        'customSnippetApiService',
        'countryApiService',
    ],

    props: {
        country: {
            type: Object as PropType<CountryEntity>,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data(): {
        advancedPostalCodePattern: string | null,
        draggedItem: DragItem | null,
        droppedItem: DragItem | null,
        snippets: TreeItem[] | [],
        customerId: string | null,
        customer: CustomerEntity | null,
        isOpenModal: boolean,
        currentPosition: number | null,
        formattingAddress: string,
        } {
        return {
            advancedPostalCodePattern: null,
            draggedItem: null,
            droppedItem: null,
            snippets: [],
            customerId: null,
            customer: null,
            isOpenModal: false,
            currentPosition: null,
            formattingAddress: '',
        };
    },

    computed: {
        customerCriteria(): CriteriaType {
            const criteria = new Criteria(1, null);
            criteria
                .addAssociation('salutation')
                .addAssociation('defaultBillingAddress.country')
                .addAssociation('defaultBillingAddress.countryState')
                .addAssociation('defaultBillingAddress.salutation');

            return criteria;
        },

        dragConf(): DragConfig {
            return {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragStart: this.dragStart,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragEnter: this.onMouseEnter,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.dragEnd,
                preventEvent: false,
            } as DragConfig;
        },

        addressFormat(): Array<Snippet[]> {
            return this.country.addressFormat;
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

            this.advancedPostalCodePattern = this.country?.advancedPostalCodePattern ?? null;
            this.$set(this.country, 'advancedPostalCodePattern', null);
        },

        'country.addressFormat'(address) {
            if (!address) {
                return;
            }

            void this.renderFormattingAddress(this.customer?.defaultBillingAddress as Address);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.advancedPostalCodePattern = cloneDeep(this.country.advancedPostalCodePattern);

            void this.getSnippets();
        },

        dragStart(config: DragConfig): void {
            this.draggedItem = config.data;
        },

        onMouseEnter(dragData: DragItem, dropData: DragItem): void {
            if (!this.draggedItem) {
                return;
            }

            if (!dragData || !dropData) {
                return;
            }

            this.droppedItem = dropData;
        },

        dragEnd(): void {
            if (!this.addressFormat?.length || !this.droppedItem || !this.draggedItem) {
                return;
            }

            if (![this.draggedItem?.index, this.droppedItem?.index]
                .every(position => typeof position === 'number')
            ) {
                return;
            }

            this.country.addressFormat = Object.assign(
                [],
                this.country.addressFormat,
                {
                    [this.draggedItem.index]: this.country.addressFormat[this.droppedItem.index],
                    [this.droppedItem.index]: this.country.addressFormat[this.draggedItem.index],
                },
            );

            this.draggedItem = null;
            this.droppedItem = null;
        },

        onDragEnd(dragPosition: number, { dragData, dropData }: { dragData: DragItem, dropData: DragItem }): void {
            // swap positions in different lines
            if (
                typeof dropData?.linePosition === 'number' &&
                typeof dragData?.linePosition === 'number' &&
                dragData.linePosition !== dropData.linePosition
            ) {
                this.$set(this.country.addressFormat[dragData.linePosition], dragData.index, dropData.snippet);
                this.$set(this.country.addressFormat[dropData.linePosition], dropData.index, dragData.snippet);
                return;
            }

            // move to another line
            this.$set(
                this.country.addressFormat,
                `${dropData.index}`,
                [...this.country.addressFormat[dropData.index], dragData.snippet],
            );

            this.country.addressFormat[dragPosition].splice(dragData.index, 1);
            this.$set(
                this.country.addressFormat,
                dragPosition,
                this.country.addressFormat[dragPosition],
            );
        },

        moveToLocation(source: number, dest: number | null): void {
            if (!this.addressFormat) {
                return;
            }

            dest = typeof dest !== 'number' ? this.addressFormat.length - 1 : dest;
            const snippet = this.country.addressFormat[source];

            this.$set(this.country, 'addressFormat', this.swapPosition(source, dest, [snippet]) ?? []);
        },

        addNewLineAt(source: number, dest: string | null): void {
            if (!this.addressFormat?.length) {
                return;
            }

            const snippet = this.addressFormat[source];
            const swag = dest === 'above' ? [[], snippet] : [snippet, []];

            this.$set(this.country, 'addressFormat', this.swapPosition(source, source, swag) ?? []);
        },

        swapPosition(source: number, dest: number, swag: Array<Snippet[]>): Array<Snippet[]>|null {
            if (!this.addressFormat?.length) {
                return null;
            }

            const newSnippets = [
                ...this.country.addressFormat.filter((_, key) => key !== source),
            ];

            newSnippets.splice(dest, 0, ...swag);

            return newSnippets;
        },

        change(index: number, newSnippet?: Snippet): void {
            if (!newSnippet) {
                this.$set(this.country, 'addressFormat', this.addressFormat.filter((_, key) => index !== key));
                return;
            }

            this.$set(this.country.addressFormat, index, newSnippet);
        },

        customerLabel(item: CustomerEntity): string {
            if (!item) {
                return '';
            }

            return `${item.firstName}, ${item.lastName}`;
        },

        onChangeCustomer(customerId: string, customer: CustomerEntity): void {
            this.customer = null;
            if (!customerId || !customer) {
                return;
            }

            this.customer = customer;

            void this.renderFormattingAddress(this.customer.defaultBillingAddress);
        },

        resetMarkup(): Promise<unknown> {
            return this.countryApiService.defaultCountryAddressFormat()
                .then((response) => {
                    const defaultAddressFormat = (response as { data: Array<Snippet[]>}).data;
                    this.$set(this.country, 'addressFormat', defaultAddressFormat);
                });
        },

        openSnippetModal(position: number) {
            this.isOpenModal = true;
            this.currentPosition = position;
        },

        onCloseModal() {
            this.currentPosition = null;
            this.isOpenModal = false;
        },

        getSnippets(): Promise<unknown> {
            return this.customSnippetApiService.snippets().then((response) => {
                const snippets = (response as { data: Snippet[] }).data;

                this.snippets = snippets?.map((snippet: Snippet) => {
                    return {
                        id: snippet.type === CUSTOM_SNIPPET_TYPE.PLAIN
                            ? `${CUSTOM_SNIPPET_TYPE.PLAIN}/${snippet.value}`
                            : snippet.value,
                        name: snippet.type === CUSTOM_SNIPPET_TYPE.PLAIN
                            ? snippet.value
                            : this.getLabelProperty(snippet.value),
                    };
                }) as TreeItem[];
                // eslint-disable-next-line @typescript-eslint/no-empty-function
            }).catch(() => {});
        },

        renderFormattingAddress(address?: Address): Promise<unknown> {
            if (!address) {
                this.formattingAddress = '';
                return Promise.resolve();
            }

            return this.customSnippetApiService
                .render(address, this.country.addressFormat)
                .then((res) => {
                    const { rendered } = (res as { rendered: string});

                    this.formattingAddress = rendered;
                });
        },

        getLabelProperty(value: string): string {
            const string = value.split('/').map((item: string) => camelCase(item)).join('.');

            return this.$te(`sw-custom-snippet.${string}`) ? this.$tc(`sw-custom-snippet.${string}`) : value;
        },
    },
});
