import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-stream-detail.html.twig';
import './sw-product-stream-detail.scss';

const { Component, Mixin, State } = Shopware;
const { warn } = Shopware.Utils.debug;

Component.register('sw-product-stream-detail', {
    template,

    inject: ['productStreamConditionService'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('productStream')
    ],

    shortcuts: {
        'SYSTEMKEY+S': 'onSave',
        ESCAPE: 'onCancel'
    },

    data() {
        return {
            nameRequired: this.isSystemLanguage(),
            productStream: {},
            nestedFilters: {},
            filterAssociations: {},
            // conditionsStore will not be used for product stream conditions
            conditionStore: {},
            isLoading: false,
            isSaveLoading: false,
            isSaveSuccessful: false,
            treeConfig: {
                entityName: 'product-stream',
                conditionIdentifier: 'filters',
                childName: 'queries',
                andContainer: {
                    type: 'multi',
                    operator: 'AND'
                },
                orContainer: {
                    type: 'multi',
                    operator: 'OR'
                },
                placeholder: {
                    type: 'equals'
                },
                getComponent(condition) {
                    let component = 'sw-product-stream-filter';

                    if (condition.type.toLowerCase() === this.andContainer.type) {
                        component = 'sw-condition-and-container';

                        if (condition.operator
                            && condition.operator.toLowerCase() === this.orContainer.operator.toLowerCase()) {
                            component = 'sw-condition-or-container';
                        }
                    }

                    return component;
                },
                isAndContainer(condition) {
                    return condition.type.toLowerCase() === this.andContainer.type
                        && condition.operator.toLowerCase() === this.andContainer.operator.toLowerCase();
                },
                isOrContainer(condition) {
                    return condition.type.toLowerCase() === this.orContainer.type
                        && condition.operator.toLowerCase() === this.orContainer.operator.toLowerCase();
                },
                isPlaceholder(condition) {
                    return (!condition.field || condition.field === 'id')
                        && (!condition.type || condition.type === 'equals')
                        && !(condition.value || (condition.parameters && Object.keys(condition.parameters).length));
                }
            },
            showModalPreview: false
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(this.identifier)
        };
    },

    computed: {
        identifier() {
            return this.placeholder(this.productStream, 'name');
        },
        productStreamStore() {
            return State.getStore('product_stream');
        },
        productStreamFilterStore() {
            return State.getStore('product_stream_filter');
        },
        customFieldSetStore() {
            return State.getStore('custom_field_set');
        },
        tooltipSave() {
            const systemKey = this.$device.getSystemKey();

            return {
                message: `${systemKey} + S`,
                appearance: 'light'
            };
        },
        tooltipCancel() {
            return {
                message: 'ESC',
                appearance: 'light'
            };
        }
    },

    created() {
        this.createdComponent();
    },

    watch: {
        '$route.params.id'() {
            this.createdComponent();
        }
    },
    beforeRouteLeave(to, from, next) {
        if (!this.showModalPreview) {
            next(true);
        }
        this.closeModalPreview().then(() => {
            next(true);
        });
    },
    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.getProductCustomFields();
                this.productStreamId = this.$route.params.id;
                if (this.productStream.isLocal) {
                    this.filterAssociations = this.productStream.getAssociation('filters');
                    return;
                }

                this.loadEntityData();
            }
        },

        isSystemLanguage() {
            const isSystem = State.getStore('language').systemLanguageId === State.getStore('language').currentLanguageId;
            return isSystem ? 'required' : null;
        },

        loadEntityData() {
            this.productStream = this.productStreamStore.getById(this.productStreamId);
            this.filterAssociations = this.productStream.getAssociation('filters');
        },

        abortOnLanguageChange() {
            return this.productStream.hasChanges();
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage() {
            this.nameRequired = this.isSystemLanguage();
            this.loadEntityData();
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            const productStreamName = this.productStream.name;
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: productStreamName }
            );
            this.isSaveLoading = true;
            const deletions = this.createDeletionQueue();
            this.isSaveSuccessful = false;

            return this.productStream.save().then(() => {
                this.isSaveLoading = false;
                this.isSaveSuccessful = true;
                this.syncDeletions(deletions);
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                this.isSaveLoading = false;
                warn(this._name, exception.message, exception.response);
            });
        },

        onCancel() {
            this.$router.push({ name: 'sw.product.stream.index' });
        },


        openModalPreview() {
            this.showModalPreview = true;
        },
        closeModalPreview() {
            return new Promise((resolve) => {
                this.showModalPreview = false;
                if (this.$refs.modalPreview) {
                    this.$refs.modalPreview.$on('destroy', () => {
                        resolve();
                    });
                }
            });
        },

        createDeletionQueue() {
            if (!this.filterAssociations.store) {
                return [];
            }

            const deletions = Object.values(this.filterAssociations.store).filter(entity => {
                return entity.isDeleted && entity.type === 'not';
            });

            deletions.forEach(deletion => this.filterAssociations.remove(deletion));

            return deletions;
        },

        syncDeletions(deletions) {
            if (!deletions.length) {
                return;
            }

            deletions.forEach(deletion => this.productStreamFilterStore.add(deletion));
            this.productStreamFilterStore.sync(true);
        },
        getProductCustomFields() {
            this.isLoading = true;

            const params = {
                criteria: CriteriaFactory.equals('relations.entityName', 'product'),
                associations: {
                    customFields: {},
                    relations: {}
                }
            };
            this.customFieldSetStore.getList(params, true).then((response) => {
                response.items.forEach((customFieldSet) => {
                    const customFields = {};
                    customFieldSet.customFields.forEach((customField) => {
                        customField = {
                            type: customField.type,
                            name: customField.name,
                            label: customField.name
                        };
                        customField = this.mapCustomFieldType(customField);
                        customFields[customField.name] = customField;
                    });
                    this.productStreamConditionService.productCustomFields = customFields;
                });
                this.isLoading = false;
            });
        },
        mapCustomFieldType(customField) {
            switch (customField.type) {
                case 'bool':
                    customField.type = 'boolean';
                    break;
                case 'html':
                case 'text':
                    customField.type = 'string';
                    break;
                case 'datetime':
                    customField.type = 'string';
                    customField.format = 'date-time';
                    break;
                case 'int':
                    customField.type = 'integer';
                    break;
                case 'float':
                    customField.type = 'number';
                    break;
                default:
                    break;
            }
            return customField;
        }
    }
});
