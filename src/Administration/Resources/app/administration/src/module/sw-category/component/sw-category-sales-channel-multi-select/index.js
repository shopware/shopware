import template from './sw-category-sales-channel-multi-select.html.twig';

const { Component } = Shopware;
const { EntityCollection } = Shopware.Data;

Component.extend('sw-category-sales-channel-multi-select', 'sw-entity-multi-select', {
    template,

    computed: {
        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },
    },

    methods: {
        isSelected(item) {
            return this.currentCollection.some((entity) => {
                return entity.id === item.id;
            });
        },

        addItem(item) {
            // Remove entry if it is in the collection already
            if (this.isSelected(item)) {
                const associationEntity = this.currentCollection.find((entity) => {
                    return entity.id === item.id;
                });

                this.remove(associationEntity);
                return;
            }

            const changedCollection = EntityCollection.fromCollection(this.currentCollection);
            changedCollection.add(item);

            this.$emit('item-add', item);
            this.emitChanges(changedCollection);
            this.onSelectExpanded();
        },
    },
});
