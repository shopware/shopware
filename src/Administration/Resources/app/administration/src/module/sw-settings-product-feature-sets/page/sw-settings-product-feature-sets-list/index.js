import template from './sw-settings-product-feature-sets-list.html.twig';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-product-feature-sets-list', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            entityName: 'product_feature_set',
            productFeatureSets: null,
            sortBy: 'product_feature_set.name',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: true,
            showDeleteModal: false
        };
    },

    computed: {
        productFeatureSetsRepository() {
            return this.repositoryFactory.create('product_feature_set');
        }
    },

    methods: {
        metaInfo() {
            return {
                title: this.$createTitle()
            };
        },

        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            this.productFeatureSetsRepository.search(criteria, Shopware.Context.api).then((items) => {
                this.total = items.total;
                this.productFeatureSets = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.StateDeprecated.getStore('language').setCurrentId(languageId);
            this.getList();
        },

        onInlineEditSave(promise, productFeatureSets) {
            promise.then(() => {
                this.createNotificationSuccess({
                    title: this.$tc('sw-settings-product-feature-sets.detail.titleSaveSuccess'),
                    message: this.$tc('sw-settings-product-feature-sets.detail.messageSaveSuccess', 0, { name: productFeatureSets.name })
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    title: this.$tc('sw-settings-product-feature-sets.detail.titleSaveError'),
                    message: this.$tc('sw-settings-product-feature-sets.detail.messageSaveError')
                });
            });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.productFeatureSetsRepository.delete(id, Shopware.Context.api).then(() => {
                this.getList();
            });
        },


        getproductFeatureSetsColumns() {
            return [{
                property: 'translated.name',
                dataIndex: 'translated.name',
                inlineEdit: 'string',
                label: 'sw-settings-product-feature-sets.list.columnTemplate',
                routerLink: 'sw.settings.product.feature.sets.detail',
                allowResize: true,
                primary: true
            },
            {
                property: 'translated.description',
                dataIndex: 'translated.description',
                inlineEdit: 'string',
                label: 'sw-settings-product-feature-sets.list.columnDescription',
                allowResize: true,
                primary: false
            },
            {
                property: 'features.id',
                dataIndex: 'features.id',
                inlineEdit: 'string',
                label: 'sw-settings-product-feature-sets.list.columnValues',
                allowResize: true,
                primary: false
            }];
        }
    }
});

