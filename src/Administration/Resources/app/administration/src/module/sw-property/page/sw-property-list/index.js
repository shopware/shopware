import template from './sw-property-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-property-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('listing')
    ],

    data() {
        return {
            propertyGroup: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'ASC',
            showDeleteModal: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        propertyRepository() {
            return this.repositoryFactory.create('property_group');
        },

        defaultCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.useNaturalSorting));
            criteria.addAssociation('options');

            return criteria;
        },

        useNaturalSorting() {
            return this.sortBy === 'property.name';
        }
    },

    methods: {
        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.propertyRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            return this.propertyRepository.search(this.defaultCriteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.propertyGroup = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getPropertyColumns() {
            return [{
                property: 'name',
                label: 'sw-property.list.columnName',
                routerLink: 'sw.property.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'options',
                label: 'sw-property.list.columnOptions',
                allowResize: true
            }, {
                property: 'description',
                label: 'sw-property.list.columnDescription',
                allowResize: true
            }];
        }
    }
});
