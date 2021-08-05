import template from './sw-product-stream-list.html.twig';
import './sw-product-stream-list.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-product-stream-list', {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            productStreams: null,
            sortBy: 'createdAt',
            sortDirection: 'DESC',
            isLoading: false,
            showDeleteModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },
    },

    methods: {
        onInlineEditSave(promise, productStream) {
            return promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-product-stream.detail.messageSaveSuccess', 0, { name: productStream.name }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-product-stream.detail.messageSaveError'),
                });
            });
        },

        onChangeLanguage() {
            return this.getList();
        },

        getList() {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            this.naturalSorting = this.sortBy === 'createdAt';
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            return this.productStreamRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.productStreams = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getProductStreamColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-product-stream.list.columnName',
                routerLink: 'sw.product.stream.detail',
                width: '250px',
                allowResize: true,
                primary: true,
            }, {
                property: 'description',
                label: 'sw-product-stream.list.columnDescription',
                width: '250px',
                allowResize: true,
            }, {
                property: 'updatedAt',
                label: 'sw-product-stream.list.columnDateUpdated',
                align: 'right',
                allowResize: true,
            }, {
                property: 'invalid',
                label: 'sw-product-stream.list.columnStatus',
                allowResize: true,
            }];
        },

        getNoPermissionsTooltip(role, showOnDisabledElements = true) {
            return {
                showDelay: 300,
                message: this.$tc('sw-privileges.tooltip.warning'),
                appearance: 'dark',
                showOnDisabledElements,
                disabled: this.acl.can(role) || this.allowDelete,
            };
        },
    },
});
