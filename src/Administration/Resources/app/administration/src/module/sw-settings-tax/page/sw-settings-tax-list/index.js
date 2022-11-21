import template from './sw-settings-tax-list.html.twig';
import './sw-settings-tax-list.scss';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory', 'acl', 'systemConfigApiService'],

    mixins: [
        Mixin.getByName('listing'),
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            tax: null,
            sortBy: 'position',
            isLoading: false,
            sortDirection: 'ASC',
            naturalSorting: false,
            showDeleteModal: false,
            defaultTaxRateId: null,
            selectedDefaultTaxRateId: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        taxRepository() {
            return this.repositoryFactory.create('tax');
        },
    },

    methods: {
        getList() {
            const criteria = new Criteria(this.page, this.limit);
            this.isLoading = true;
            this.naturalSorting = this.sortBy === 'name';

            criteria.setTerm(this.term);
            criteria.addSorting(Criteria.sort(this.sortBy, this.sortDirection, this.naturalSorting));
            if (this.sortBy !== 'name') {
                // Add second sorting, to make sorting deterministic
                criteria.addSorting(Criteria.sort('name', 'ASC', true));
            }

            this.getDefaultTaxRate().then((defaultRate) => {
                this.defaultTaxRateId = defaultRate;
                this.selectedDefaultRate = defaultRate;
            });

            this.taxRepository.search(criteria).then((items) => {
                this.total = items.total;
                this.tax = items;
                this.isLoading = false;

                return items;
            }).catch(() => {
                this.isLoading = false;
            });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            this.getList();
        },

        async onInlineEditSave(promise, tax) {
            promise.then(() => {
                if (this.selectedDefaultTaxRateId === this.defaultTaxRateId) {
                    this.createNotificationSuccess({
                        message: this.$tc('sw-settings-tax.detail.messageSaveSuccess', 0, { name: tax.name }),
                    });

                    return;
                }

                this.systemConfigApiService.saveValues({ 'core.tax.defaultTaxRate': this.selectedDefaultTaxRateId })
                    .then(() => {
                        this.defaultTaxRateId = this.selectedDefaultTaxRateId;

                        this.createNotificationSuccess({
                            message: this.$tc('sw-settings-tax.detail.messageSaveSuccess', 0, { name: tax.name }),
                        });
                    })
                    .catch(() => {
                        this.getList();

                        this.createNotificationError({
                            message: this.$tc('sw-settings-tax.detail.messageSaveError'),
                        });
                    });
            }).catch(() => {
                this.createNotificationError({
                    message: this.$tc('sw-settings-tax.detail.messageSaveError'),
                });
            });
        },

        async onInlineEditCancel(promise) {
            await promise;

            this.selectedDefaultTaxRateId = null;

            this.getDefaultTaxRate().then((defaultRate) => {
                this.defaultTaxRateId = defaultRate;
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

            return this.taxRepository.delete(id).then(() => {
                this.getList();
            });
        },

        getTaxColumns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                inlineEdit: 'string',
                label: 'sw-settings-tax.list.columnName',
                routerLink: 'sw.settings.tax.detail',
                width: '250px',
                primary: true,
            }, {
                property: 'taxRate',
                inlineEdit: 'number',
                label: 'sw-settings-tax.list.columnDefaultTaxRate',
            }, {
                property: 'default',
                inlineEdit: 'boolean',
                label: 'sw-settings-tax.list.columnDefault',
            }];
        },

        isShopwareDefaultTax(tax) {
            return this.$te(`global.tax-rates.${tax.name}`, 'en-GB');
        },

        getLabel(tax) {
            return this.isShopwareDefaultTax(tax) ? this.$tc(`global.tax-rates.${tax.name}`) : tax.name;
        },

        isSelectedDefaultRate(tax) {
            return this.defaultTaxRateId === tax.id;
        },

        setSelectedDefaultRate(checkBoxValue, id) {
            this.selectedDefaultTaxRateId = checkBoxValue ? id : null;
        },

        getDefaultTaxRate() {
            return this.systemConfigApiService
                .getValues('core.tax')
                .then(response => response['core.tax.defaultTaxRate'] ?? null)
                .catch(() => null);
        },
    },
};
