import template from './sw-settings-shipping-list.html.twig';
import './sw-settings-shipping-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

/**
 * @package checkout
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            shippingMethods: null,
            isLoading: false,
            sortBy: 'name',
            sortDirection: 'ASC',
            skeletonItemAmount: 3,
            showDeleteModal: false,
            searchConfigEntity: 'shipping_method',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        shippingRepository() {
            return this.repositoryFactory.create('shipping_method');
        },

        columns() {
            return [{
                property: 'name',
                label: 'sw-settings-shipping.list.columnName',
                inlineEdit: 'string',
                routerLink: 'sw.settings.shipping.detail',
                allowResize: true,
                primary: true,
            }, {
                property: 'description',
                label: 'sw-settings-shipping.list.columnDescription',
                inlineEdit: 'string',
                allowResize: true,
            }, {
                property: 'taxType',
                label: 'sw-settings-shipping.list.columnTaxType',
                inlineEdit: 'string',
                allowResize: true,
            }, {
                property: 'active',
                label: 'sw-settings-shipping.list.columnActive',
                inlineEdit: 'boolean',
                allowResize: true,
                align: 'center',
            }, {
                property: 'position',
                label: 'sw-settings-shipping.list.columnPosition',
                inlineEdit: 'number',
                allowResize: true,
                align: 'center',
            }];
        },

        listingCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria.addSorting(
                Criteria.sort('name', 'ASC'),
            );

            return criteria;
        },

        shippingCostTaxOptions() {
            return [{
                label: this.$tc('sw-settings-shipping.shippingCostOptions.auto'),
                value: 'auto',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.highest'),
                value: 'highest',
            }, {
                label: this.$tc('sw-settings-shipping.shippingCostOptions.fixed'),
                value: 'fixed',
            }];
        },
    },

    methods: {
        async getList() {
            this.isLoading = true;

            const criteria = await this.addQueryScores(this.term, this.listingCriteria);
            if (!this.entitySearchable) {
                this.isLoading = false;
                this.total = 0;

                return;
            }

            if (this.freshSearchTerm) {
                criteria.resetSorting();
            }

            this.shippingRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.shippingMethods = items;

                return items;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        onInlineEditSave(item) {
            this.isLoading = true;
            const name = item.name || item.translated.name;

            return this.entityRepository.save(item)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-shipping.list.messageSaveSuccess', 0, { name }),
                    });
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-shipping.list.messageSaveError', 0, { name }),
                    });
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onConfirmDelete(id) {
            const name = this.shippingMethods.find((item) => item.id === id).name;

            this.onCloseDeleteModal();
            this.shippingRepository.delete(id)
                .then(() => {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-shipping.list.messageDeleteSuccess', 0, { name }),
                    });
                }).catch(() => {
                    this.createNotificationError({
                        message: this.$tc('sw-settings-shipping.list.messageDeleteError', 0, { name }),
                    });
                }).finally(() => {
                    this.showDeleteModal = null;
                    this.getList();
                });
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        shippingTaxTypeLabel(taxName) {
            if (!taxName) {
                return '';
            }

            const tax = this.shippingCostTaxOptions.find((i) => taxName === i.value) || '';

            return tax?.label;
        },
    },
};
