const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.extend('sw-entity-tag-select', 'sw-entity-multi-select', {
    data() {
        return {
            tagExists: true,
        };
    },

    methods: {
        resetActiveItem(position = 0) {
            this.$refs.swSelectResultList.setActiveItemIndex(position);
        },

        search(searchTerm) {
            // Remove earlier "Add Tag" elements
            this.filterSearchGeneratedTags();

            Promise.all([this.checkTagExists(this.searchTerm), this.$super('search', searchTerm)]).then(() => {
                // Add the "Add Tag" Element if no tag exists
                if (!this.tagExists) {
                    // Create dummy entity with id -1
                    const newTag = this.repository.create(this.entityCollection.context, -1);
                    newTag.name = this.$tc('global.sw-tag-field.listItemAdd', 0, { term: this.searchTerm });

                    this.resultCollection.unshift(newTag);
                    // Reset active item position, so that the "Add Tag" element gets focus
                    this.$nextTick(this.resetActiveItem);
                }
            });
        },

        addItem(item) {
            if (item.id === -1) {
                this.createNewTag();
            } else {
                this.$super('addItem', item);
            }
        },

        createNewTag() {
            const item = this.repository.create(this.entityCollection.context);
            item.name = this.searchTerm;
            this.repository.save(item, this.entityCollection.context).then(() => {
                this.addItem(item);

                // Reset criteria and all parameter to get a clean new result after an item has been added
                this.criteria.setPage(1);
                this.criteria.setLimit(this.resultLimit);
                this.criteria.setTerm('');
                this.searchTerm = '';
                this.resultCollection = null;

                this.loadData().then(() => {
                    this.resetActiveItem();
                });
            });
        },

        checkTagExists(term) {
            if (term.trim().length === 0) {
                this.tagExists = true;
                return Promise.resolve();
            }

            const criteria = new Criteria();
            criteria.addFilter(
                Criteria.equals('name', term),
            );

            return this.repository.search(criteria, this.context).then((response) => {
                this.tagExists = response.total > 0;
            });
        },

        filterSearchGeneratedTags() {
            this.resultCollection = this.resultCollection.filter(entity => {
                return entity.id !== -1;
            });
        },
    },
});
