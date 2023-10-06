import template from './sw-entity-advanced-selection-modal-grid.html.twig';

const { Component } = Shopware;

/**
 * @private
 * @status prototype
 */
Component.extend('sw-entity-advanced-selection-modal-grid', 'sw-entity-listing', {
    template,

    props: {
        isRecordSelectable: {
            type: Function,
            required: false,
            default(item) {
                const isSelectableByDefault = !this.reachMaximumSelectionExceed ||
                    Object.keys(this.selection).includes(item[this.itemIdentifierProperty]);
                let isSelectableByRestrictions = true;

                if (
                    this.isRecordSelectableCallback !== null &&
                    this.isRecordSelectableCallback !== undefined
                ) {
                    const callbackResult = this.isRecordSelectableCallback(item);

                    if (callbackResult.isSelectable !== null && callbackResult.isSelectable !== undefined) {
                        isSelectableByRestrictions = callbackResult.isSelectable;
                    }
                }

                return isSelectableByDefault && isSelectableByRestrictions;
            },
        },

        isRecordSelectableCallback: {
            type: Function,
            required: false,
            default() {
                return true;
            },
        },
    },

    computed: {
        isSelectAllDisabled() {
            if (!this.maximumSelectItems) {
                return false;
            }

            if (!this.records) {
                return false;
            }

            const isSomeRecordSelectable = this.records.some(item => {
                return this.isRecordSelectable(item);
            });

            if (!isSomeRecordSelectable) {
                return true;
            }

            const currentVisibleIds = this.records.map(record => record.id);

            return this.reachMaximumSelectionExceed
                && Object.keys(this.selection).every(id => !currentVisibleIds.includes(id));
        },

        allSelectedChecked() {
            if (this.isSelectAllDisabled) {
                return false;
            }

            if (this.reachMaximumSelectionExceed) {
                return true;
            }

            if (!this.records || this.records.length === 0) {
                return false;
            }

            const selectedItems = Object.values(this.selection);
            const isSomeRecordSelectable = this.records.some(item => {
                return this.isRecordSelectable(item);
            });

            if (!isSomeRecordSelectable) {
                return false;
            }

            return this.records.every(item => {
                if (!this.isRecordSelectable(item)) {
                    return true;
                }

                return selectedItems.some((selection) => {
                    return selection[this.itemIdentifierProperty] === item[this.itemIdentifierProperty];
                });
            });
        },
    },

    methods: {
        getSelectableTooltip(item) {
            if (
                this.isRecordSelectableCallback === null ||
                this.isRecordSelectableCallback === undefined
            ) {
                return { message: '', disabled: true };
            }

            const callbackResult = this.isRecordSelectableCallback(item);

            if (!callbackResult.tooltip) {
                return { message: '', disabled: true };
            }

            return callbackResult.tooltip;
        },
    },
});
