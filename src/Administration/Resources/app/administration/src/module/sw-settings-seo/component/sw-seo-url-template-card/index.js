/**
 * @sw-package inventory
 */

import template from './sw-seo-url-template-card.html.twig';
import './sw-seo-url-template-card.scss';

const { Mixin, Defaults } = Shopware;
const { mapCollectionPropertyErrors } = Shopware.Component.getComponentHelper();
const EntityCollection = Shopware.Data.EntityCollection;
const Criteria = Shopware.Data.Criteria;
const utils = Shopware.Utils;

const INVALID_HEADLESS_TEMPLATE_ERROR_CODE = 'CONTENT__INVALID_HEADLESS_SEO_URL_TEMPLATE';

// Store-api SEO URL routes are identified by their route name prefix. Headless sales channels generate
// SEO URLs via these routes instead of the storefront routes, so they get their own template entities.
const STORE_API_ROUTE_PREFIX = 'store-api.';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'seoUrlTemplateService',
        'seoUrlService',
        'repositoryFactory',
    ],

    emits: ['sales-channel-changed'],

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
            headlessSeoUrlRoutes: [],
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

        salesChannelSupportsSeoUrlTemplates() {
            const currentSalesChannel = this.salesChannels.find((entity) => {
                return entity.id === this.salesChannelId;
            });

            if (!currentSalesChannel) {
                return true;
            }

            // Product comparison and agentic commerce sales channels do not serve SEO URLs, so maintaining
            // templates for them has no effect and is not offered.
            return ![
                Defaults.productComparisonTypeId,
                Defaults.agenticCommerceTypeId,
            ].includes(currentSalesChannel.typeId);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.seoUrlTemplateRepository = this.repositoryFactory.create('seo_url_template');
            this.defaultSeoUrlTemplates = new EntityCollection(
                this.seoUrlTemplateRepository.route,
                this.seoUrlTemplateRepository.schema.entity,
                Shopware.Context.api,
                new Criteria(1, 25),
            );

            this.seoUrlPreviewCriteria['frontend.navigation.page'] = new Criteria(1, 25).addFilter(
                Criteria.not('and', [Criteria.equals('path', null)]),
            );

            this.fetchSalesChannels();
            this.fetchHeadlessSeoUrlRoutes();
            this.fetchSeoUrlTemplates();
        },
        fetchHeadlessSeoUrlRoutes() {
            return this.seoUrlService.getStoreApiConfigs().then((configs) => {
                this.headlessSeoUrlRoutes = configs;
            });
        },
        fetchSeoUrlTemplates(salesChannelId = null) {
            const criteria = new Criteria(1, 25);

            this.seoUrlTemplates = new EntityCollection(
                this.seoUrlTemplateRepository.route,
                this.seoUrlTemplateRepository.schema.entity,
                Shopware.Context.api,
                criteria,
            );

            if (!salesChannelId) {
                salesChannelId = null;
            }
            criteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));

            this.isLoading = true;

            this.seoUrlTemplateRepository.search(criteria).then((response) => {
                response.forEach((entity) => {
                    if (!this.seoUrlTemplates.has(entity.id)) {
                        this.seoUrlTemplates.add(entity);
                    }
                });

                if (!salesChannelId) {
                    // Save the defaults as blueprint for creating dynamically new entities
                    response.forEach((entity) => {
                        if (!this.defaultSeoUrlTemplates.has(entity)) {
                            this.defaultSeoUrlTemplates.add(entity);
                        }
                    });
                } else {
                    this.createSeoUrlTemplatesFromDefaultRoutes(salesChannelId);
                }
                this.isLoading = false;

                this.seoUrlTemplates.forEach((seoUrlTemplate) => {
                    // Fetch preview / validate seo url template
                    this.fetchSeoUrlPreview(seoUrlTemplate);

                    // Create stores for the possible variables
                    if (!this.variableStores.hasOwnProperty(seoUrlTemplate.id)) {
                        this.seoUrlTemplateService.getContext(seoUrlTemplate).then((data) => {
                            this.createVariableOptions(seoUrlTemplate.id, data);
                        });
                    }
                });
            });
        },
        createSeoUrlTemplatesFromDefaultRoutes(salesChannelId) {
            // Iterate over the default seo url templates and create new entities for the actual sales channel
            // if they do not exist
            this.defaultSeoUrlTemplates.forEach((defaultEntity) => {
                let routeName = defaultEntity.routeName;
                if (this.salesChannelIsHeadless) {
                    routeName =
                        this.headlessSeoUrlRoutes.find((config) => defaultEntity.entityName === config.entityName)
                            ?.routeName ?? defaultEntity.routeName;
                }

                const entityAlreadyExists = this.seoUrlTemplates.some((entity) => {
                    return entity.routeName === routeName && entity.salesChannelId === salesChannelId;
                });

                if (!entityAlreadyExists) {
                    const entity = this.seoUrlTemplateRepository.create();
                    entity.routeName = routeName;
                    entity.salesChannelId = salesChannelId;
                    entity.entityName = defaultEntity.entityName;
                    entity.template = null;
                    this.seoUrlTemplates.add(entity);
                }
            });

            // Keep the field order aligned with the default templates, matched by entity name.
            const entityNameOrder = this.defaultSeoUrlTemplates.map((defaultEntity) => defaultEntity.entityName);
            this.seoUrlTemplates.sort(
                (a, b) => entityNameOrder.indexOf(a.entityName) - entityNameOrder.indexOf(b.entityName),
            );

            if (!this.salesChannelIsHeadless) {
                return;
            }

            this.headlessSeoUrlRoutes
                .filter(
                    (config) =>
                        !this.seoUrlTemplates.some(
                            (entity) => entity.routeName === config.routeName && entity.salesChannelId === salesChannelId,
                        ),
                )
                .map((config) => this.getHeadlessSeoUrlTemplate(config.entityName, config.routeName, salesChannelId))
                .forEach((template) => this.seoUrlTemplates.add(template));
        },
        getHeadlessSeoUrlTemplate(entityName, routeName, salesChannelId) {
            const entity = this.seoUrlTemplateRepository.create();
            entity.routeName = routeName;
            entity.salesChannelId = salesChannelId;
            entity.entityName = entityName;
            entity.template = null;

            return entity;
        },
        isHeadlessRoute(routeName) {
            return typeof routeName === 'string' && routeName.startsWith(STORE_API_ROUTE_PREFIX);
        },
        createVariableOptions(id, data) {
            const storeOptions = [];

            Object.entries(data).forEach(
                ([
                    property,
                    value,
                ]) => {
                    storeOptions.push({ name: `${property}` });

                    if (value instanceof Object) {
                        Object.keys(value).forEach((innerProperty) => {
                            storeOptions.push({
                                name: `${property}.${innerProperty}`,
                            });
                        });
                    }
                },
            );

            this.variableStores[id] = storeOptions;
        },
        getVariableOptions(id) {
            if (this.variableStores.hasOwnProperty(id)) {
                return this.variableStores[id];
            }
            return false;
        },
        getLabel(seoUrlTemplate) {
            const routeName = seoUrlTemplate.routeName.replace(/\./g, '-');
            if (this.$t(`sw-seo-url-template-card.routeNames.${routeName}`)) {
                return this.$t(`sw-seo-url-template-card.routeNames.${routeName}`);
            }

            return seoUrlTemplate.routeName;
        },
        getPlaceholder(seoUrlTemplate) {
            // Headless templates have no inherited default value.
            if (!seoUrlTemplate.salesChannelId || this.isHeadlessRoute(seoUrlTemplate.routeName)) {
                return null;
            }

            const defaultEntity = Object.values(this.defaultSeoUrlTemplates).find((entity) => {
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

            const templatesToSync = this.seoUrlTemplates.filter((entry) => entry.template !== null);

            this.seoUrlTemplateRepository
                .sync(templatesToSync)
                .then(() => {
                    this.seoUrlTemplates = new EntityCollection(
                        this.seoUrlTemplateRepository.route,
                        this.seoUrlTemplateRepository.schema.entity,
                        Shopware.Context.api,
                        new Criteria(1, 25),
                    );
                    this.fetchSeoUrlTemplates(this.salesChannelId);
                    this.createSaveSuccessNotification();
                })
                .catch(() => {
                    this.createSaveErrorNotification();
                });
        },
        createSaveErrorNotification() {
            const titleSaveSuccess = this.$t('global.default.error');
            const messageSaveSuccess = this.$t('sw-seo-url-template-card.general.messageSaveError');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess,
            });
        },
        createSaveSuccessNotification() {
            const titleSaveSuccess = this.$t('global.default.success');
            const messageSaveSuccess = this.$t('sw-seo-url-template-card.general.messageSaveSuccess');

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess,
            });
        },

        onSelectInput(propertyName, entity) {
            if (propertyName === null) {
                return;
            }
            const templateValue = entity.template ? `${entity.template}/` : '';
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
                        this.setErrorMessagesForEntity(entity);
                    }
                }, 400);
            } else {
                this.setErrorMessagesForEntity(entity);
            }

            this.debouncedPreviews[entity.id]();
        },
        setErrorMessagesForEntity(entity, error = null) {
            this.errorMessages[entity.id] = error;
        },
        fetchSeoUrlPreview(entity) {
            this.previewLoadingStates[entity.id] = true;

            const criteria = this.seoUrlPreviewCriteria[entity.routeName]
                ? this.seoUrlPreviewCriteria[entity.routeName]
                : new Criteria(1, 25);
            entity.criteria = criteria.parse();
            this.seoUrlTemplateService
                .preview(entity)
                .then((response) => {
                    this.noEntityError = this.noEntityError.filter((elem) => {
                        return elem !== entity.id;
                    });

                    this.previews[entity.id] = response;

                    if (response === null) {
                        this.noEntityError.push(entity.id);
                    } else {
                        this.setErrorMessagesForEntity(entity);
                    }
                    this.previewLoadingStates[entity.id] = false;
                })
                .catch((err) => {
                    const error = err.response.data.errors[0];
                    if (error.code === INVALID_HEADLESS_TEMPLATE_ERROR_CODE) {
                        error.detail = this.$t('sw-seo-url-template-card.general.invalidHeadlessUrlTemplate');
                    }
                    this.setErrorMessagesForEntity(entity, error);

                    this.previews[entity.id] = [];

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
            this.$emit('sales-channel-changed', this.salesChannelIsHeadless);
            this.fetchSeoUrlTemplates(salesChannelId);
        },
        getTemplatesForSalesChannel(salesChannelId) {
            return this.seoUrlTemplates.filter((templateEntity) => {
                return templateEntity.salesChannelId === salesChannelId;
            });
        },
    },
};
