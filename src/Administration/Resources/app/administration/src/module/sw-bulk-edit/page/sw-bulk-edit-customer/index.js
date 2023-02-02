import template from './sw-bulk-edit-customer.html.twig';
import './sw-bulk-edit-customer.scss';
import swBulkEditState from '../../state/sw-bulk-edit.state';

const { Mixin } = Shopware;
const { Criteria } = Shopware.Data;
const { types } = Shopware.Utils;
const { chunk } = Shopware.Utils.array;
const { cloneDeep } = Shopware.Utils.object;

/**
 * @package system-settings
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'feature',
        'bulkEditApiFactory',
        'repositoryFactory',
    ],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            isLoading: false,
            isLoadedData: false,
            isSaveSuccessful: false,
            bulkEditData: {},
            customFieldSets: [],
            processStatus: '',
            customer: {},
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        selectedIds() {
            return Shopware.State.get('shopwareApps').selectedIds;
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        customerRepository() {
            return this.repositoryFactory.create('customer');
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, null);

            criteria.addFilter(Criteria.equals('relations.entityName', 'customer'));

            return criteria;
        },

        hasChanges() {
            const customFieldsValue = this.bulkEditData.customFields?.value;
            const hasFieldsChanged = Object.values(this.bulkEditData).some((field) => field.isChanged);
            const hasCustomFieldsChanged = !types.isEmpty(customFieldsValue) && Object.keys(customFieldsValue).length > 0;

            return hasFieldsChanged || hasCustomFieldsChanged;
        },

        actionsRequestGroup() {
            return [{
                value: 'accept',
                label: this.$tc('sw-bulk-edit.customer.account.customerGroupRequest.options.accept'),
            }, {
                value: 'decline',
                label: this.$tc('sw-bulk-edit.customer.account.customerGroupRequest.options.decline'),
            }];
        },

        accountFormFields() {
            return [{
                name: 'groupId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'customer_group',
                    changeLabel: this.$tc('sw-bulk-edit.customer.account.customerGroup.label'),
                    placeholder: this.$tc('sw-bulk-edit.customer.account.customerGroup.placeholder'),
                },
            }, {
                name: 'defaultPaymentMethodId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'payment_method',
                    changeLabel: this.$tc('sw-bulk-edit.customer.account.defaultPaymentMethod.label'),
                    placeholder: this.$tc('sw-bulk-edit.customer.account.defaultPaymentMethod.placeholder'),
                },
            }, {
                name: 'active',
                type: 'bool',
                config: {
                    type: 'switch',
                    changeLabel: this.$tc('sw-bulk-edit.customer.account.status.label'),
                },
            }, {
                name: 'languageId',
                config: {
                    componentName: 'sw-entity-single-select',
                    entity: 'language',
                    changeLabel: this.$tc('sw-bulk-edit.customer.account.language.label'),
                    placeholder: this.$tc('sw-bulk-edit.customer.account.language.placeholder'),
                },
            }, {
                name: 'requestedCustomerGroupId',
                labelHelpText: this.$tc('sw-bulk-edit.customer.account.customerGroupRequest.helpText'),
                config: {
                    componentName: 'sw-single-select',
                    entity: 'customer_group',
                    changeLabel: this.$tc('sw-bulk-edit.customer.account.customerGroupRequest.label'),
                    placeholder: this.$tc('sw-bulk-edit.customer.account.customerGroupRequest.placeholder'),
                    options: this.actionsRequestGroup,
                },
            }];
        },

        tagsFormFields() {
            return [
                {
                    name: 'tags',
                    config: {
                        componentName: 'sw-entity-tag-select',
                        entityCollection: this.customer.tags,
                        allowOverwrite: true,
                        allowClear: true,
                        allowAdd: true,
                        allowRemove: true,
                        changeLabel: this.$tc('sw-bulk-edit.order.tags.changeLabel'),
                        placeholder: this.$tc('sw-bulk-edit.order.tags.placeholder'),
                    },
                },
            ];
        },
    },

    beforeCreate() {
        Shopware.State.registerModule('swBulkEdit', swBulkEditState);
    },

    created() {
        this.createdComponent();
    },

    beforeDestroy() {
        Shopware.State.unregisterModule('swBulkEdit');
    },

    methods: {
        createdComponent() {
            this.setRouteMetaModule();
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            this.isLoading = true;

            this.customer = this.customerRepository.create(Shopware.Context.api);

            this.loadCustomFieldSets().then(() => {
                this.loadBulkEditData();
                this.isLoadedData = true;
            }).catch(error => {
                this.createNotificationError({
                    title: this.$tc('global.default.error'),
                    message: error,
                });
            }).finally(() => {
                this.isLoading = false;
            });
        },

        setRouteMetaModule() {
            this.$set(this.$route.meta.$module, 'color', '#F88962');
            this.$set(this.$route.meta.$module, 'icon', 'regular-users');
        },

        defineBulkEditData(name, value = null, type = 'overwrite', isChanged = false) {
            if (this.bulkEditData[name]) {
                return;
            }

            this.$set(this.bulkEditData, name, {
                isChanged: isChanged,
                type: type,
                value: value,
            });
        },

        loadBulkEditData() {
            const bulkEditFormGroups = [
                this.accountFormFields,
                this.tagsFormFields,
            ];

            bulkEditFormGroups.forEach((bulkEditForms) => {
                bulkEditForms.forEach((bulkEditForm) => {
                    this.defineBulkEditData(bulkEditForm.name);
                });
            });

            this.$set(this.bulkEditData, 'customFields', {
                type: 'overwrite',
                value: null,
            });
        },

        loadCustomFieldSets() {
            return this.customFieldSetRepository.search(this.customFieldSetCriteria).then((res) => {
                this.customFieldSets = res;
            });
        },

        onCustomFieldsChange(value) {
            if (Object.keys(value).length <= 0) {
                this.bulkEditData = this.bulkEditData.filter(change => change.field !== 'customFields');
                return;
            }

            this.bulkEditData.customFields.value = value;
        },

        onProcessData() {
            const data = {
                requestData: [],
                syncData: [],
            };

            Object.keys(this.bulkEditData).forEach(key => {
                const bulkEditField = cloneDeep(this.bulkEditData[key]);

                let bulkEditValue = this.customer[key];

                if (key === 'active' && !bulkEditValue) {
                    bulkEditValue = false;
                }

                if (key === 'customFields') {
                    bulkEditValue = bulkEditField.value;
                }

                const change = {
                    field: key,
                    type: bulkEditField.type,
                    value: bulkEditValue,
                };

                if (bulkEditField.isChanged || (key === 'customFields' && bulkEditField.value)) {
                    if (key === 'requestedCustomerGroupId') {
                        data.requestData.push(change);
                    } else {
                        data.syncData.push(change);
                    }
                }
            });

            return data;
        },

        openModal() {
            this.$router.push({ name: 'sw.bulk.edit.customer.save' });
        },

        async onSave() {
            this.isLoading = true;
            const { requestData, syncData } = this.onProcessData();
            const bulkEditCustomerHandler = this.bulkEditApiFactory.getHandler('customer');
            const payloadChunks = chunk(this.selectedIds, 50);
            const requests = [];

            if (requestData.length) {
                requests.push(bulkEditCustomerHandler.bulkEditRequestedGroup(this.selectedIds, requestData));
            }

            payloadChunks.forEach(payload => {
                if (syncData.length) {
                    requests.push(bulkEditCustomerHandler.bulkEdit(payload, syncData));
                }
            });

            return Promise.all(requests)
                .then(() => {
                    this.processStatus = 'success';
                }).catch((e) => {
                    console.error(e);
                    this.processStatus = 'fail';
                }).finally(() => {
                    this.isLoading = false;
                });
        },

        closeModal() {
            this.$router.push({ name: 'sw.bulk.edit.customer' });
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
        },
    },
};
