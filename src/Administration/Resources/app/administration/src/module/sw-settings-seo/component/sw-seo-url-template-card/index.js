/**
 * @package sales-channel
 */

import template from './sw-seo-url-template-card.html.twig';
import './sw-seo-url-template-card.scss';

const { Mixin } = Shopware;
const { mapCollectionPropertyErrors } = Shopware.Component.getComponentHelper();
const EntityCollection = Shopware.Data.EntityCollection;
const Criteria = Shopware.Data.Criteria;
const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['seoUrlTemplateService', 'repositoryFactory'],

    mixins: [Mixin.getByName('notification')],


    data() {
        return {
            defaultSeoUrlTemplates: null,
            seoUrlTemplates: null,
            seoUrlPreviewCriteria: {},
            isLoading: true,
            debouncedPreviews: {},
            previewLoadingStates: {},
            errorMessages: {},
            previews: {},
            noEntityError: [],
            variableStores: {},
            seoUrlTemplateRepository: {},
            salesChannelId: null,
            salesChannels: [],
            selectedProperty: null,
        };
    },

    computed: {
        ...mapCollectionPropertyErrors('seoUrlTemplates', ['template']),

        salesChannelRepository() {
            return this.repositoryFactory.create('sales_channel');
        },

        salesChannelIsHeadless() {
            const currentSalesChannel = this.salesChannels.find((entity) => {
                return entity.id === this.salesChannelId;
            });

            if (!currentSalesChannel) {
                return false;
            }

            // from Defaults.php
            return currentSalesChannel.typeId === 'f183ee5650cf4bdb8a774337575067a6';
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.seoUrlTemplateRepository = this.repositoryFactory.create('seo_url_template');
            this.seoUrlTemplates = new EntityCollection(
                this.seoUrlTemplateRepository.route,
                this.seoUrlTemplateRepository.schema.entity,
                Shopware.Context.api,
                new Criteria(1, 25),
            );

            this.defaultSeoUrlTemplates = new EntityCollection(
                this.seoUrlTemplateRepository.route,
                this.seoUrlTemplateRepository.schema.entity,
                Shopware.Context.api,
                new Criteria(1, 25),
            );

            this.seoUrlPreviewCriteria['frontend.navigation.page'] =
                (new Criteria(1, 25)).addFilter(
                    Criteria.not('and', [Criteria.equals('path', null)]),
                );

            this.fetchSalesChannels();
            this.fetchSeoUrlTemplates();
        },
        fetchSeoUrlTemplates(salesChannelId = null) {
            const criteria = new Criteria(1, 25);

            if (!salesChannelId) {
                salesChannelId = null;
            }
            criteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));

            this.isLoading = true;

            this.seoUrlTemplateRepository.search(criteria).then((response) => {
                response.forEach(entity => {
                    if (!this.seoUrlTemplates.has(entity.id)) {
                        this.seoUrlTemplates.add(entity);
                    }
                });

                if (!salesChannelId) {
                    // Save the defaults as blueprint for creating dynamically new entities
                    response.forEach(entity => {
                        if (!this.defaultSeoUrlTemplates.has(entity)) {
                            this.defaultSeoUrlTemplates.add(entity);
                        }
                    });
                } else {
                    this.createSeoUrlTemplatesFromDefaultRoutes(salesChannelId);
                }
                this.isLoading = false;

                this.seoUrlTemplates.forEach(seoUrlTemplate => {
                    // Fetch preview / validate seo url template
                    this.fetchSeoUrlPreview(seoUrlTemplate);

                    // Create stores for the possible variables
                    if (!this.variableStores.hasOwnProperty(seoUrlTemplate.id)) {
                        this.seoUrlTemplateService.getContext(seoUrlTemplate).then(data => {
                            this.createVariableOptions(seoUrlTemplate.id, data);
                        });
                    }
                });
            });
        },
        createSeoUrlTemplatesFromDefaultRoutes(salesChannelId) {
            // Iterate over the default seo url templates and create new entities for the actual sales channel
            // if they do not exist
            this.defaultSeoUrlTemplates.forEach(defaultEntity => {
                const entityAlreadyExists = this.seoUrlTemplates.some((entity) => {
                    return entity.routeName === defaultEntity.routeName && entity.salesChannelId === salesChannelId;
                });

                if (!entityAlreadyExists) {
                    const entity = this.seoUrlTemplateRepository.create();
                    entity.routeName = defaultEntity.routeName;
                    entity.salesChannelId = salesChannelId;
                    entity.entityName = defaultEntity.entityName;
                    entity.template = null;
                    this.seoUrlTemplates.add(entity);
                }
            });
        },
        createVariableOptions(id, data) {
            const storeOptions = [];

            Object.entries(data).forEach(([property, value]) => {
                storeOptions.push({ name: `${property}` });

                if (value instanceof Object) {
                    Object.keys(value).forEach((innerProperty) => {
                        storeOptions.push({ name: `${property}.${innerProperty}` });
                    });
                }
            });

            this.$set(this.variableStores, id, storeOptions);
        },
        getVariableOptions(id) {
            if (this.variableStores.hasOwnProperty(id)) {
                return this.variableStores[id];
            }
            return false;
        },
        getLabel(seoUrlTemplate) {
            const routeName = seoUrlTemplate.routeName.replace(/\./g, '-');
            if (this.$tc(`sw-seo-url-template-card.routeNames.${routeName}`)) {
                return this.$tc(`sw-seo-url-template-card.routeNames.${routeName}`);
            }

            return seoUrlTemplate.routeName;
        },
        getPlaceholder(seoUrlTemplate) {
            if (!seoUrlTemplate.salesChannelId) {
                return null;
            }

            const defaultEntity = Object.values(this.defaultSeoUrlTemplates).find(entity => {
                return entity.routeName === seoUrlTemplate.routeName;
            });

            return defaultEntity.template;
        },
        onClickSave() {
            const hasError = Object.keys(this.errorMessages).some((key) => {
                return this.errorMessages[key] !== null;
            });

            if (hasError) {
                this.createSaveErrorNotification();
                return;
            }


            this.seoUrlTemplates.forEach((entry) => {
                if (entry.template === null) {
                    this.seoUrlTemplates.remove(entry.id);
                }
            });

            this.seoUrlTemplateRepository.sync(this.seoUrlTemplates).then(() => {
                this.seoUrlTemplates = new EntityCollection(
                    this.seoUrlTemplateRepository.route,
                    this.seoUrlTemplateRepository.schema.entity,
                    Shopware.Context.api,
                    new Criteria(1, 25),
                );
                this.fetchSeoUrlTemplates(this.salesChannelId);
                this.createSaveSuccessNotification();
            }).catch(() => {
                this.createSaveErrorNotification();
            });
        },
        createSaveErrorNotification() {
            const titleSaveSuccess = this.$tc('global.default.error');
            const messageSaveSuccess = this.$tc('sw-seo-url-template-card.general.messageSaveError');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess,
            });
        },
        createSaveSuccessNotification() {
            const titleSaveSuccess = this.$tc('global.default.success');
            const messageSaveSuccess = this.$tc('sw-seo-url-template-card.general.messageSaveSuccess');

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess,
            });
        },

        onSelectInput(propertyName, entity) {
            if (propertyName === null) {
                return;
            }
            const templateValue = entity.template ? (`${entity.template}/`) : '';
            entity.template = `${templateValue}{{ ${propertyName} }}`;
            this.fetchSeoUrlPreview(entity);
        },
        onInput(entity) {
            this.debouncedPreviewSeoUrlTemplate(entity);
        },
        debouncedPreviewSeoUrlTemplate(entity) {
            if (!this.debouncedPreviews[entity.id]) {
                this.debouncedPreviews[entity.id] = utils.debounce(() => {
                    if (entity.template && entity.template !== '') {
                        this.fetchSeoUrlPreview(entity);
                    } else {
                        this.$set(this.errorMessages, entity.id, null);
                    }
                }, 400);
            } else {
                this.$set(this.errorMessages, entity.id, null);
            }

            this.debouncedPreviews[entity.id]();
        },
        fetchSeoUrlPreview(entity) {
            this.$set(this.previewLoadingStates, entity.id, true);
            const criteria = this.seoUrlPreviewCriteria[entity.routeName]
                ? this.seoUrlPreviewCriteria[entity.routeName] : (new Criteria(1, 25));
            entity.criteria = criteria.parse();
            this.seoUrlTemplateService.preview(entity).then((response) => {
                this.noEntityError = this.noEntityError.filter((elem) => {
                    return elem !== entity.id;
                });
                this.$set(this.previews, entity.id, response);
                if (response === null) {
                    this.noEntityError.push(entity.id);
                } else {
                    this.$set(this.errorMessages, entity.id, null);
                }
                this.previewLoadingStates[entity.id] = false;
            }).catch(err => {
                this.$set(this.errorMessages, entity.id, err.response.data.errors[0].detail);
                this.$set(this.previews, entity.id, []);
                this.previewLoadingStates[entity.id] = false;
            });
        },
        fetchSalesChannels() {
            this.salesChannelRepository.search(new Criteria(1, 25)).then((response) => {
                this.salesChannels = response;
            });
        },
        onSalesChannelChanged(salesChannelId) {
            this.salesChannelId = salesChannelId;
            this.fetchSeoUrlTemplates(salesChannelId);
        },
        getTemplatesForSalesChannel(salesChannelId) {
            return this.seoUrlTemplates.filter((templateEntity) => {
                return templateEntity.salesChannelId === salesChannelId;
            });
        },
    },
};
