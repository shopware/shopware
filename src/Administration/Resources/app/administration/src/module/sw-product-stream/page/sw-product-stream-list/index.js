/*
 * @package business-ops
 */

import template from './sw-product-stream-list.html.twig';
import './sw-product-stream-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

/**
 * @private
 */
export default {
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
            searchConfigEntity: 'product_stream',
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

        async getList() {
            this.isLoading = true;

            let criteria = new Criteria(this.page, this.limit);

            criteria.setTerm(this.term);
            if (this.acl.can('category:read')) {
                criteria.addAggregation(
                    Criteria.terms(
                        'product_stream',
                        'id',
                        null,
                        null,
                        Criteria.count('categories', 'product_stream.categories.id'),
                    ),
                );
            }
            this.naturalSorting = this.sortBy === 'createdAt';
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));

            criteria = await this.addQueryScores(this.term, criteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return false;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

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

        onDeleteItemFailed({ id, errorResponse }) {
            const stream = this.productStreams?.get(id);
            const message = errorResponse?.response?.data?.errors?.[0]?.detail || null;

            if (!stream) {
                return;
            }

            if (!this.productStreams.aggregations.product_stream) {
                this.createNotificationError({ message });
                return;
            }

            const aggregation = this.productStreams.aggregations.product_stream.buckets.filter((bucket) => {
                return bucket.key === id;
            }).at(0);

            const count = aggregation.categories.count;
            const name = stream.name;

            if (count === 0) {
                this.createNotificationError({ message });
                return;
            }

            this.createNotificationError({
                message: this.$tc('sw-product-stream.general.errorCategory', count, { name, count }),
            });
        },

        onDeleteItemsFailed({ selectedIds, errorResponse }) {
            selectedIds.forEach((id) => {
                this.onDeleteItemFailed({ id, errorResponse });
            });
        },

        onDuplicate(item) {
            const behavior = {
                cloneChildren: true,
                overwrites: {
                    name: `${item.name || item.translated.name} ${this.$tc('global.default.copy')}`,
                },
            };

            this.isLoading = true;

            this.productStreamRepository.clone(item.id, Shopware.Context.api, behavior).then((clone) => {
                const route = { name: 'sw.product.stream.detail', params: { id: clone.id } };

                this.$router.push(route);
            }).catch(() => {
                this.isLoading = false;

                this.createNotificationError({
                    message: this.$tc('global.notification.unspecifiedSaveErrorMessage'),
                });
            });
        },
    },
};
