import template from './sw-settings-units.html.twig';
import './sw-settings-units.scss';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-settings-units', {
    template,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: true,
            placeholderAmount: 7,
            unitsCriteria: null,
            units: [],
            newUnit: null,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        unitRepository() {
            return this.repositoryFactory.create('unit');
        },

        unitList() {
            if (this.newUnit) {
                return [...this.units, this.newUnit];
            }

            return this.units;
        },

        isEmpty() {
            return this.unitList.length <= 0;
        },

        tooltipCreate() {
            if (!this.acl.can('scale_unit.creator')) {
                return {
                    message: this.$tc('sw-privileges.tooltip.warning'),
                    disabled: this.acl.can('scale_unit.creator'),
                    showOnDisabledElements: true,
                };
            }

            return {
                showOnDisabledElements: true,
                message: this.$tc('sw-settings-units.general.disableAddNewUnitMessage'),
                disabled: !this.isAddingUnitsDisabled,
            };
        },

        isAddingUnitsDisabled() {
            return Shopware.Context.api.languageId !== Shopware.Context.api.systemLanguageId;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.unitsCriteria = this.createUnitsCriteria();
            this.loadUnits();
        },

        createUnitsCriteria() {
            const criteria = new Criteria(1, 500);

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        loadUnits() {
            this.isLoading = true;

            this.unitRepository.search(this.unitsCriteria).then((searchResult) => {
                this.units = searchResult;
                this.placeholderAmount = searchResult.total;
                this.isLoading = false;
            });
        },

        createNewUnit() {
            this.newUnit = this.unitRepository.create();
            this.newUnit.name = '';
            this.newUnit.shortCode = '';

            this.activateInlineEdit(this.newUnit.id);
        },

        saveUnit(unit) {
            this.isLoading = true;

            this.unitRepository.save(unit).then(() => {
                this.isLoading = false;

                this.loadUnits();
                this.newUnit = null;

                // throw success notification
                const titleSaveSuccess = this.$tc('global.default.success');
                const messageSaveSuccess = this.$tc('sw-settings-units.notification.successMessage');

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess,
                });
            }).catch(() => {
                this.isLoading = false;

                // reactivate inline editing
                if (this.newUnit && unit.id === this.newUnit.id) {
                    this.activateInlineEdit(unit.id);
                }

                // throw error notification
                const titleSaveError = this.$tc('global.default.error');
                const messageSaveError = this.$tc('sw-settings-units.notification.errorMessage');

                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError,
                });
            });
        },

        cancelUnit() {
            this.loadUnits();
            this.newUnit = null;
        },

        deleteUnit(unit) {
            this.isLoading = true;
            this.unitRepository.delete(unit.id).then(() => {
                this.isLoading = false;
                this.loadUnits();
            });
        },

        activateInlineEdit(id) {
            this.$refs.swDataGrid.currentInlineEditId = id;
            this.$refs.swDataGrid.isInlineEditActive = true;
        },

        unitColumns() {
            return [{
                property: 'name',
                label: 'sw-settings-units.grid.columnName',
                inlineEdit: 'string',
            }, {
                property: 'shortCode',
                label: 'sw-settings-units.grid.columnShortCode',
                inlineEdit: 'string',
            }];
        },

        onChangeLanguage() {
            this.loadUnits();
        },
    },
});
