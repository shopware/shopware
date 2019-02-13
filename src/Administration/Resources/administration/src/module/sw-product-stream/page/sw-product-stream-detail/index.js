import { Component, Entity, Mixin, State } from 'src/core/shopware';
import { warn } from 'src/core/service/utils/debug.utils';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-product-stream-detail.html.twig';
import './sw-product-stream-detail.scss';

Component.register('sw-product-stream-detail', {
    template,

    mixins: [
        Mixin.getByName('placeholder'),
        Mixin.getByName('notification'),
        Mixin.getByName('discard-detail-page-changes')('productStream')
    ],

    data() {
        return {
            productStream: {},
            nestedFilters: {},
            filterAssociations: {},
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

                        if (condition.operator.toLowerCase() === this.orContainer.operator.toLowerCase()) {
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
                    return condition.field === 'product' && !(condition.value || Object.keys(condition.parameters).length);
                }
            }
        };
    },

    computed: {
        productStreamStore() {
            return State.getStore('product_stream');
        },
        productStreamFilterStore() {
            return State.getStore('product_stream_filter');
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

    methods: {
        createdComponent() {
            if (this.$route.params.id) {
                this.productStreamId = this.$route.params.id;
                if (this.productStream.isLocal) {
                    return;
                }

                this.loadEntityData();
            }
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
            this.loadEntityData();
        },

        getDefinitionStore() {
            // TODO: NEXT-1709 Move definitionStore to treeConfig.conditionStore
            const definition = Entity.getDefinition('product');
            Object.keys(definition.properties).forEach((key) => {
                definition.properties[key].name = key;
            });

            return new LocalStore(Object.values(definition.properties), 'name');
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

            return this.productStream.save().then(() => {
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
        }
    }
});
