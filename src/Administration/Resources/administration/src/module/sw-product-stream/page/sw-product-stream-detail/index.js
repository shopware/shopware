import { Component, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import CriteriaFactory from 'src/core/factory/criteria.factory';
import template from './sw-product-stream-detail.html.twig';
import './sw-product-stream-detail.scss';

Component.register('sw-product-stream-detail', {
    template,

    inject: ['productStreamConditionService'],

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('productStream')
    ],

    data() {
        return {
            nameRequired: this.isSystemLanguage(),
            productStream: {},
            nestedFilters: {},
            filterAssociations: {},
            // conditionsStore will not be used for product stream conditions
            conditionStore: {},
            isLoading: false,
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
        attributeSetStore() {
            return State.getStore('attribute_set');
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
                this.getProductAttributes();
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

        onSave() {
            const productStreamName = this.productStream.name;
            const titleSaveSuccess = this.$tc('sw-product-stream.detail.titleSaveSuccess');
            const messageSaveSuccess = this.$tc(
                'sw-product-stream.detail.messageSaveSuccess', 0, { name: productStreamName }
            );
            const titleSaveError = this.$tc('global.notification.notificationSaveErrorTitle');
            const messageSaveError = this.$tc(
                'global.notification.notificationSaveErrorMessage', 0, { entityName: productStreamName }
            );

            const deletions = this.createDeletionQueue();

            return this.productStream.save().then(() => {
                this.syncDeletions(deletions);

                this.createNotificationSuccess({
                    title: titleSaveSuccess,
                    message: messageSaveSuccess
                });
            }).catch((exception) => {
                this.createNotificationError({
                    title: titleSaveError,
                    message: messageSaveError
                });
                warn(this._name, exception.message, exception.response);
            });
        },
        openModalPreview() {
            this.showModalPreview = true;
        },
        closeModalPreview() {
            return new Promise((resolve) => {
                this.showModalPreview = false;
                if (this.$refs.modalPreview) {
                    this.$refs.modalPreview.$on('sw-product-stream-modal-preview-destroy', () => {
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
        getProductAttributes() {
            this.isLoading = true;

            const params = {
                criteria: CriteriaFactory.equals('relations.entityName', 'product'),
                associations: {
                    attributes: {},
                    relations: {}
                }
            };
            this.attributeSetStore.getList(params, true).then((response) => {
                response.items.forEach((attributeSet) => {
                    const attributes = {};
                    attributeSet.attributes.forEach((attribute) => {
                        attribute = {
                            type: attribute.type,
                            name: attribute.name,
                            label: attribute.name
                        };
                        attribute = this.mapAttributeType(attribute);
                        attributes[attribute.name] = attribute;
                    });
                    this.productStreamConditionService.productAttributes = attributes;
                });
                this.isLoading = false;
            });
        },
        mapAttributeType(attribute) {
            switch (attribute.type) {
            case 'bool':
                attribute.type = 'boolean';
                break;
            case 'html':
            case 'text':
                attribute.type = 'string';
                break;
            case 'datetime':
                attribute.type = 'string';
                attribute.format = 'date-time';
                break;
            case 'int':
                attribute.type = 'integer';
                break;
            case 'float':
                attribute.type = 'number';
                break;
            default:
                break;
            }
            return attribute;
        }
    }
});
