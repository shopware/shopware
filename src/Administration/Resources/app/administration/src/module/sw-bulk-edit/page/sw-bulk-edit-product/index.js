import template from './sw-bulk-edit-product.html.twig';
import './sw-bulk-edit-product.scss';
import swBulkEditProductState from './state';

const { Component, Mixin } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-bulk-edit-product', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            bulkEditData: {},
            customFieldSets: []
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        bulkEditProduct() {
            return Shopware.State.get('swBulkEditProduct');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 100);

            criteria.addFilter(Criteria.equals('relations.entityName', 'product'));
            criteria
                .getAssociation('customFields')
                .addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

            return criteria;
        }
    },

    beforeCreate() {
        Shopware.State.registerModule('swBulkEditProduct', swBulkEditProductState);
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swBulkEditProduct');
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.bulkEditService = Shopware.Service('bulkEditService');

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(status) {
            this.bulkEditProduct.customFields.isChange = status;
        },

        onProcessData() {
            const data = [];
            Object.keys(this.bulkEditProduct).forEach(key => {
                const item = this.bulkEditProduct[key];
                if (item.isChange) {
                    data.push({
                        field: key,
                        type: item.type,
                        value: item.value
                    });
                }
            });

            return data;
        },

        async onSave() {
            this.isLoading = true;

            const data = this.onProcessData();
            await this.bulkEditService.bulkEdit('product', this.selectedIds, data).then(() => {
                this.isLoading = false;

                // TODO implement the success notification modal here
                this.createNotificationSuccess({
                    message: 'Edit successful'
                });
            }).catch((error) => {
                this.isLoading = false;

                // TODO implement the error notification modal here
                this.createNotificationError({
                    message: 'Error'
                });

                throw error;
            });
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
        }
    }
});

