import { Component } from 'src/core/shopware';
import template from './sw-product-visibility-select.html.twig';

Component.extend('sw-product-visibility-select', 'sw-select', {
    template,

    methods: {
        loadSelections() {
            this.isLoadingSelections = true;

            const params = {
                associations: { salesChannel: {} }
            };

            this.associationStore.getAll(params).then((items) => {
                this.selections = items;
                this.isLoadingSelections = false;
            });
        },

        isInSelections(item) {
            return !this.selections.every((selection) => {
                return selection.salesChannelId !== item.id;
            });
        },

        getBySalesChannelId(salesChannelId) {
            let item = null;

            this.associationStore.each((visibility) => {
                if (visibility.salesChannelId === salesChannelId) {
                    item = visibility;
                }
            });

            return item;
        },

        addSelection({ item }) {
            if (item === undefined || !item.id) {
                return;
            }

            if (this.isInSelections(item)) {
                return;
            }

            const salesChannelId = item.id;

            if (!this.isInSelections(salesChannelId)) {
                const newItem = this.associationStore.create();

                newItem.setLocalData({
                    salesChannelId: salesChannelId,
                    salesChannelInternal: item,
                    // full visible
                    visibility: 30
                });

                this.selections.push(newItem);
            } else {
                const visibility = this.getBySalesChannelId(salesChannelId);

                // In case the entity was already created but was deleted before
                visibility.isDeleted = false;
            }

            this.searchTerm = '';

            this.setFocus();
        },

        dismissSelection(id) {
            if (!id) {
                return;
            }

            if (!this.associationStore.hasId(id)) {
                return;
            }

            const entity = this.associationStore.store[id];
            entity.delete();

            this.selections = Object.values(this.associationStore.store).filter((item) => {
                return item.isDeleted === false;
            });
        }
    }
});
