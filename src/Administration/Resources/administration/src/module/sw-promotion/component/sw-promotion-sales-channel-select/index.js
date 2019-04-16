import { Component } from 'src/core/shopware';
import StoreLoader from 'src/core/helper/store-loader.helper';
import template from './sw-promotion-sales-channel-select.html.twig';

Component.extend('sw-promotion-sales-channel-select', 'sw-select', {
    template,

    methods: {
        loadSelected() {
            this.isLoadingSelections = true;

            const params = {

                associations: { salesChannel: {} }
            };

            const loader = new StoreLoader();
            loader.loadAll(this.associationStore, params).then((items) => {
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
                let idToRemove = null;
                this.associationStore.forEach((ine) => {
                    if (ine.salesChannelId === item.id) {
                        idToRemove = ine.id;
                    }
                });

                this.dismissSelection(idToRemove);
                return;
            }

            const salesChannelId = item.id;

            if (!this.isInSelections(salesChannelId)) {
                const newItem = this.associationStore.create();

                newItem.setLocalData({
                    salesChannelId: salesChannelId,
                    salesChannelInternal: item,
                    // full visible
                    priority: 1
                });

                this.selections.push(newItem);
                this.selected.push(item);
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

            this.deletedItems.push(id);
            this.selections = this.selections.filter((entry) => entry.id !== id);
            this.selected = this.selected.filter((entry) => entry.id !== id);

            const entity = this.associationStore.store[id];
            entity.delete();

            this.selections = Object.values(this.associationStore.store).filter((item) => {
                return item.isDeleted === false;
            });
        }
    }
});
