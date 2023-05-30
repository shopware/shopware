/**
 * @package system-settings
 */
import template from './sw-settings-number-range-list.html.twig';
import './sw-settings-number-range-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('notification'),
        Mixin.getByName('listing'),
        Mixin.getByName('placeholder'),
    ],

    data() {
        return {
            entityName: 'number_range',
            numberRange: null,
            sortBy: 'name',
            isLoading: false,
            sortDirection: 'DESC',
            naturalSorting: true,
            showDeleteModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        filters() {
            return [];
        },

        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },

        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },

        numberRangeRepository() {
            return this.repositoryFactory.create('number_range');
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            criteria.addAssociation('type');
            criteria.addAssociation('numberRangeSalesChannels');
            criteria.addAssociation('numberRangeSalesChannels.salesChannel');

            this.numberRangeRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.numberRange = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        getNumberRangeColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: 'sw-settings-number-range.list.columnName',
                routerLink: 'sw.settings.number.range.detail',
                primary: true,
                inlineEdit: 'string',
            }, {
                property: 'type.typeName',
                label: 'sw-settings-number-range.list.columnUsedIn',
            }, {
                property: 'global',
                label: 'sw-settings-number-range.list.columnAssignment',
            }];
        },

        onDelete(id) {
            this.showDeleteModal = id;
        },

        onCloseDeleteModal() {
            this.showDeleteModal = false;
        },

        onConfirmDelete(id) {
            this.showDeleteModal = false;

            return this.numberRangeRepository.delete(id).then(() => {
                this.getList();
            });
        },

        onChangeLanguage() {
            this.getList();
        },

        onInlineEditSave(promise, numberRange) {
            promise.then(() => {
                this.createNotificationSuccess({
                    message: this.$tc('sw-settings-number-range.detail.messageSaveSuccess', 0, { name: numberRange.name }),
                });
            }).catch(() => {
                this.getList();
                this.createNotificationError({
                    message: this.$tc('sw-settings-number-range.detail.messageSaveError'),
                });
            });
        },
    },
};
