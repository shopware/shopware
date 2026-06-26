import camelCase from 'lodash-es/camelCase';
import type CriteriaType from 'src/core/data/criteria.data';
import type { DragConfig } from 'src/app/directive/dragdrop.directive';
import template from './sw-settings-country-address-handling.html.twig';
import './sw-settings-country-address-handling.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;
const { cloneDeep } = Shopware.Utils.object;

interface TreeItem {
    id: string;
    name: string;
    parentId?: string | null;
}

interface DragItem {
    index: number;
    linePosition?: number | null;
    snippet: string[];
    targetIndex?: number;
}

interface SnippetDragPreview {
    dragIndex: number;
    sourceLinePosition: number;
    linePosition: number;
    targetIndex: number;
    snippet: string[];
}

interface RowDragPreview {
    dragIndex: number;
    targetIndex: number;
}

interface AddressFormatRow {
    index: number;
    isPlaceholder: boolean;
    isSource: boolean;
    key: string;
    snippet: string[];
}

const DefaultAddressFormat = [
    [
        'address/company',
        'symbol/dash',
        'address/department',
    ],
    [
        'address/first_name',
        'address/last_name',
    ],
    ['address/street'],
    [
        'address/zipcode',
        'address/city',
    ],
    ['address/country'],
] as string[][];
const PREVIEW_LOADING_HIDE_DELAY = 300 as number;

/**
 * @sw-package fundamentals@discovery
 *
 * @private
 */
export default Component.wrapComponentConfig({
    template,

    inject: [
        'acl',
        'customSnippetApiService',
    ],

    props: {
        country: {
            type: Object as PropType<EntitySchema.Entities['country']>,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    data(): {
        advancedPostalCodePattern: string | null;
        draggedItem: DragItem | null;
        droppedItem: DragItem | null;
        snippets: TreeItem[] | [];
        customerId: string | null;
        customer: Entity<'customer'> | null;
        isOpenModal: boolean;
        currentPosition: number | null;
        formattingAddress: string;
        isPreviewLoading: boolean;
        previewRenderToken: number;
        snippetDragPreview: SnippetDragPreview | null;
        snippetDragItem: DragItem | null;
        rowDragPreview: RowDragPreview | null;
        rowKeys: WeakMap<string[], string>;
        rowKeyCounter: number;
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
            isPreviewLoading: false,
            previewRenderToken: 0,
            snippetDragPreview: null,
            snippetDragItem: null,
            rowDragPreview: null,
            rowKeys: new WeakMap<string[], string>(),
            rowKeyCounter: 0,
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

        dragConf(): DragConfig<DragItem> {
            return {
                delay: 200,
                dragGroup: 'sw-multi-snippet',
                validDragCls: 'is--valid-drag',
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragStart: this.onDragStart,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDragEnter: this.onDragEnter,
                // eslint-disable-next-line @typescript-eslint/unbound-method
                onDrop: this.onDrop,
                preventEvent: false,
            } as DragConfig<DragItem>;
        },

        addressFormat(): Array<string[]> {
            return this.country.addressFormat as Array<string[]>;
        },

        addressFormatRows(): AddressFormatRow[] {
            const rows = this.addressFormat.map((snippet, index) => {
                return {
                    index,
                    isPlaceholder: false,
                    isSource: false,
                    key: this.getRowKey(snippet),
                    snippet,
                };
            });

            if (!this.rowDragPreview) {
                return rows;
            }

            const sourceIndex = rows.findIndex((row) => row.index === this.rowDragPreview?.dragIndex);
            const sourceRow = rows[sourceIndex];

            if (!sourceRow) {
                return rows;
            }

            sourceRow.isSource = true;
            rows.splice(this.rowDragPreview.targetIndex, 0, {
                index: this.rowDragPreview.targetIndex,
                isPlaceholder: true,
                isSource: false,
                key: `row-placeholder-${sourceRow.key}`,
                snippet: sourceRow.snippet,
            });

            return rows;
        },

        hasDefaultPostalCodePattern(): boolean {
            return !!this.country.defaultPostalCodePattern;
        },

        disabledAdvancedPostalCodePattern(): boolean {
            if (!this.hasDefaultPostalCodePattern) {
                return false;
            }

            return !this.country.checkPostalCodePattern;
        },

        isDisplayStateInRegistrationActive(): boolean {
            return !!this.country.forceStateInRegistration || !!this.country.displayStateInRegistration;
        },

        isDisplayStateInRegistrationDisabled(): boolean {
            return !!this.country.forceStateInRegistration || !this.acl.can('country.editor');
        },
    },

    watch: {
        'country.checkPostalCodePattern'(value) {
            if (value) {
                return;
            }

            this.updateCountry('checkAdvancedPostalCodePattern', false);
        },

        'country.checkAdvancedPostalCodePattern'(value) {
            if (value) {
                if (this.country.advancedPostalCodePattern && !this.advancedPostalCodePattern) {
                    return;
                }

                this.$emit(
                    'update:country',
                    'advancedPostalCodePattern',
                    this.advancedPostalCodePattern || this.country.defaultPostalCodePattern,
                );
                return;
            }

            if (!this.hasDefaultPostalCodePattern) {
                this.updateCountry('checkPostalCodePattern', value);
            }

            this.advancedPostalCodePattern = this.country?.advancedPostalCodePattern ?? null;
            this.updateCountry('advancedPostalCodePattern', null);
        },

        'country.addressFormat': {
            deep: true,
            handler(address) {
                if (!address) {
                    return;
                }

                void this.renderFormattingAddress(this.customer?.defaultBillingAddress);
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent(): void {
            this.advancedPostalCodePattern = cloneDeep(this.country.advancedPostalCodePattern) as string | null;

            void this.getSnippets();
        },

        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        onDragStart(dragConfig: DragConfig<DragItem>, draggedElement: Element, dragElement: Element): void {
            this.draggedItem = dragConfig.data;
            this.rowDragPreview =
                typeof dragConfig.data?.index === 'number'
                    ? {
                          dragIndex: dragConfig.data.index,
                          targetIndex: dragConfig.data.index,
                      }
                    : null;
        },

        onSnippetDragStart({ config }: { config: DragConfig<DragItem> }): void {
            this.snippetDragItem = config.data;
        },

        onDragEnter(dragData: DragItem, dropData: DragItem): void {
            if (!this.draggedItem) {
                return;
            }

            if (!dragData || !dropData) {
                return;
            }

            this.droppedItem = dropData;
            this.rowDragPreview = {
                dragIndex: dragData.index,
                targetIndex: this.getRowTargetIndex(this.getRowDropPosition(dropData)),
            };
        },

        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        onDrop(dragData: DragItem, dropData: DragItem): void {
            this.snippetDragPreview = null;
            this.snippetDragItem = null;
            const draggedItem = this.draggedItem;
            const droppedItem = this.droppedItem;
            const rowDragPreview = this.rowDragPreview;
            this.draggedItem = null;
            this.droppedItem = null;
            this.rowDragPreview = null;

            if (!this.addressFormat?.length || !droppedItem || !draggedItem) {
                return;
            }

            if (
                ![
                    draggedItem.index,
                    droppedItem.index,
                ].every((position) => typeof position === 'number')
            ) {
                return;
            }

            const dropPosition = this.getRowDropPosition(droppedItem);
            const draggedSnippet = this.addressFormat[draggedItem.index];

            if (!draggedSnippet) {
                return;
            }

            const newAddressFormat = this.swapPosition(
                draggedItem.index,
                this.getRowDropIndex(draggedItem.index, rowDragPreview?.targetIndex ?? this.getRowTargetIndex(dropPosition)),
                [
                    draggedSnippet,
                ],
            );

            if (newAddressFormat) {
                this.updateCountry('addressFormat', newAddressFormat);
            }
        },

        getRowTargetIndex(dropIndex: number): number {
            const currentTargetIndex = this.rowDragPreview?.targetIndex ?? this.draggedItem?.index ?? dropIndex;

            return dropIndex < currentTargetIndex ? dropIndex : dropIndex + 1;
        },

        getRowDropPosition(dropData: DragItem): number {
            return typeof dropData.linePosition === 'number' ? dropData.linePosition : dropData.index;
        },

        getRowDropIndex(dragIndex: number, targetIndex: number): number {
            return targetIndex > dragIndex ? targetIndex - 1 : targetIndex;
        },

        getRowKey(snippet: string[]): string {
            let key = this.rowKeys.get(snippet);

            if (!key) {
                this.rowKeyCounter += 1;
                key = `row-${this.rowKeyCounter}`;
                this.rowKeys.set(snippet, key);
            }

            return key;
        },

        onDropEnd(dragPosition: number, { dragData, dropData }: { dragData: DragItem; dropData: DragItem }): void {
            this.snippetDragPreview = null;
            this.snippetDragItem = null;

            if (typeof dragData?.linePosition === 'number' && dropData) {
                const targetLinePosition =
                    typeof dropData.linePosition === 'number' ? dropData.linePosition : dropData.index;
                const targetLine = [...(this.addressFormat[targetLinePosition] ?? [])];
                const sourceLine = [...(this.addressFormat[dragData.linePosition] ?? [])];
                const targetIndex =
                    typeof dropData.targetIndex === 'number'
                        ? dropData.targetIndex
                        : typeof dropData.linePosition === 'number'
                          ? dropData.index
                          : targetLine.length;

                if (dragData.linePosition === targetLinePosition) {
                    return;
                }

                const [snippet] = sourceLine.splice(dragData.index, 1);

                if (!snippet) {
                    return;
                }

                targetLine.splice(targetIndex, 0, snippet);

                this.updateCountry(`addressFormat[${targetLinePosition}]`, targetLine);
                this.updateCountry(`addressFormat[${dragData.linePosition}]`, sourceLine);
                return;
            }

            this.$emit('update:country', `addressFormat[${dropData.index}]`, [
                // @ts-expect-error - value exists
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
                ...this.country.addressFormat[dropData.index],
                dragData.snippet,
            ]);

            // @ts-expect-error - value exists
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,@typescript-eslint/no-unsafe-member-access
            this.country.addressFormat[dragPosition].splice(dragData.index, 1);

            // @ts-expect-error - value exists
            this.updateCountry(`addressFormat[${dragPosition}]`, this.country.addressFormat[dragPosition]);
        },

        onSnippetDragEnter({ dragData, dropData }: { dragData: DragItem; dropData: DragItem }): void {
            this.snippetDragPreview = null;

            if (typeof dragData?.linePosition !== 'number' || !dropData || dragData.linePosition === dropData.linePosition) {
                return;
            }

            const linePosition = typeof dropData.linePosition === 'number' ? dropData.linePosition : dropData.index;
            const targetLine = this.addressFormat[linePosition] ?? [];
            const isRowDrop = typeof dropData.linePosition !== 'number' && typeof dropData.targetIndex !== 'number';

            if (isRowDrop && targetLine.length > 0) {
                return;
            }

            const targetIndex = typeof dropData.targetIndex === 'number' ? dropData.targetIndex : targetLine.length;

            this.snippetDragPreview = {
                dragIndex: dragData.index,
                sourceLinePosition: dragData.linePosition,
                linePosition,
                targetIndex,
                snippet: dragData.snippet,
            };
        },

        moveToNewPosition(source: number, dest: number | null): void {
            if (!this.addressFormat) {
                return;
            }

            dest = typeof dest !== 'number' ? this.addressFormat.length - 1 : dest;
            // @ts-expect-error - value exists
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const snippet = this.country.addressFormat[source];

            // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            this.updateCountry('addressFormat', this.swapPosition(source, dest, [snippet]) ?? []);
        },

        addNewLineAt(source: number, dest: string | null): void {
            if (!this.addressFormat?.length) {
                return;
            }

            const snippet = this.addressFormat[source];
            const swag =
                dest === 'above'
                    ? [
                          [],
                          snippet,
                      ]
                    : [
                          snippet,
                          [],
                      ];

            this.updateCountry('addressFormat', this.swapPosition(source, source, swag) ?? []);
        },

        swapPosition(source: number, dest: number, swag: Array<string[]>): Array<string[]> | null {
            if (!this.addressFormat?.length) {
                return null;
            }

            const newSnippets = [
                // @ts-expect-error - value exists
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-call
                ...this.country.addressFormat.filter((_, key) => key !== source),
            ];

            newSnippets.splice(dest, 0, ...swag);

            // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return newSnippets;
        },

        change(index: number, newSnippet?: string): void {
            this.snippetDragPreview = null;
            this.snippetDragItem = null;

            if (!newSnippet) {
                this.updateCountry(
                    'addressFormat',
                    this.addressFormat.filter((_, key) => index !== key),
                );

                return;
            }

            this.updateCountry(`addressFormat[${index}]`, newSnippet);
        },

        customerLabel(item: Entity<'customer'>): string {
            if (!item) {
                return '';
            }

            return `${item.firstName}, ${item.lastName}`;
        },

        onChangeCustomer(customerId: string, customer: Entity<'customer'>): void {
            this.customer = null;
            if (!customerId || !customer) {
                return;
            }

            this.customer = customer;

            void this.renderFormattingAddress(this.customer.defaultBillingAddress);
        },

        resetMarkup(): void {
            this.updateCountry('addressFormat', cloneDeep(DefaultAddressFormat));
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
            return this.customSnippetApiService
                .snippets()
                .then((response) => {
                    const snippets = (response as { data: string[] }).data;

                    this.snippets = snippets?.map((snippet: string) => {
                        return {
                            id: snippet,
                            name: this.getLabelProperty(snippet),
                        };
                    });
                })
                .catch(() => {});
        },

        renderFormattingAddress(address?: EntitySchema.Entities['customer_address']): Promise<unknown> {
            this.previewRenderToken += 1;
            const previewRenderToken = this.previewRenderToken;

            if (!address) {
                this.formattingAddress = '';
                this.isPreviewLoading = false;
                return Promise.resolve();
            }

            this.isPreviewLoading = true;

            return (
                this.customSnippetApiService
                    // @ts-expect-error - value exists
                    .render(address, this.country.addressFormat)
                    .then((res) => {
                        const { rendered } = res as { rendered: string };

                        if (previewRenderToken !== this.previewRenderToken) {
                            return;
                        }

                        this.formattingAddress = rendered;
                    })
                    .finally(() => {
                        window.setTimeout(() => {
                            if (previewRenderToken === this.previewRenderToken) {
                                this.isPreviewLoading = false;
                            }
                        }, PREVIEW_LOADING_HIDE_DELAY);
                    })
            );
        },

        getLabelProperty(value: string): string {
            const string = value
                .split('/')
                .map((item: string) => camelCase(item))
                .join('.');

            return this.$te(`sw-custom-snippet.${string}`) ? this.$t(`sw-custom-snippet.${string}`) : value;
        },

        updateCountry(path: string, value: unknown): void {
            this.$emit('update:country', path, value);
        },
    },
});
