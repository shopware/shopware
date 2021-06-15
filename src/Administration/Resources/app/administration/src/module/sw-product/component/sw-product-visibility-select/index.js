import template from './sw-product-visibility-select.html.twig';

const { Component } = Shopware;
const { EntityCollection } = Shopware.Data;
const { mapState } = Shopware.Component.getComponentHelper();

Component.extend('sw-product-visibility-select', 'sw-entity-multi-select', {
    template,

    data() {
        return {
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
        ]),
        repository() {
            return this.repositoryFactory.create('sales_channel');
        },
        associationRepository() {
            return this.repositoryFactory.create('product_visibility');
        },
    },

    methods: {
        isSelected(item) {
            return this.currentCollection.some(entity => {
                return entity.salesChannelId === item.id;
            });
        },

        addItem(item) {
            // Remove when already selected
            if (this.isSelected(item)) {
                const associationEntity = this.currentCollection.find(entity => {
                    return entity.salesChannelId === item.id;
                });
                this.remove(associationEntity);
                return;
            }

            // Create new entity
            const newSalesChannelAssociation = this.associationRepository.create(this.entityCollection.context);
            newSalesChannelAssociation.productId = this.product.id;
            newSalesChannelAssociation.productVersionId = this.product.versionId;
            newSalesChannelAssociation.salesChannelId = item.id;
            newSalesChannelAssociation.visibility = 30;
            newSalesChannelAssociation.salesChannel = item;

            this.$emit('item-add', item);

            const changedCollection = EntityCollection.fromCollection(this.currentCollection);
            changedCollection.add(newSalesChannelAssociation);

            this.emitChanges(changedCollection);
            this.onSelectExpanded();
        },
    },
});
