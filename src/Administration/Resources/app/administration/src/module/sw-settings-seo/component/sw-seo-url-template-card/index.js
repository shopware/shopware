/**
 * @sw-package inventory
 */

import template from './sw-seo-url-template-card.html.twig';
import './sw-seo-url-template-card.scss';

const { Mixin } = Shopware;
const { mapCollectionPropertyErrors } = Shopware.Component.getComponentHelper();
const EntityCollection = Shopware.Data.EntityCollection;
const Criteria = Shopware.Data.Criteria;
const utils = Shopware.Utils;

/**
 * Maps the storefront SEO URL routes to their store-api counterparts used for headless sales channels.
 * Headless sales channels generate SEO URLs via the store-api routes instead of the storefront routes,
 * so they get their own template entities (without inherited default values).
 */
const HEADLESS_SEO_URL_ROUTES = [
    { storefrontRouteName: 'frontend.detail.page', routeName: 'store-api.product.detail', entityName: 'product' },
    { storefrontRouteName: 'frontend.navigation.page', routeName: 'store-api.category.detail', entityName: 'category' },
    { storefrontRouteName: 'frontend.landing.page', routeName: 'store-api.landing-page.detail', entityName: 'landing_page' },
];

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'seoUrlTemplateService',
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

            this.seoUrlPreviewCriteria['frontend.navigation.page'] = new Criteria(1, 25).addFilter(
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
                } else if (this.salesChannelIsHeadless) {
                    this.createHeadlessSeoUrlTemplates(salesChannelId);
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
        createHeadlessSeoUrlTemplates(salesChannelId) {
            // Headless sales channels do not inherit the storefront defaults. Create an empty template entity
            // per store-api route so the user can configure them without any default value.
            HEADLESS_SEO_URL_ROUTES.forEach((route) => {
                const entityAlreadyExists = this.seoUrlTemplates.some((entity) => {
                    return entity.routeName === route.routeName && entity.salesChannelId === salesChannelId;
                });

                if (entityAlreadyExists) {
                    return;
                }

                const entity = this.seoUrlTemplateRepository.create();
                entity.routeName = route.routeName;
                entity.salesChannelId = salesChannelId;
                entity.entityName = route.entityName;
                entity.template = null;
                this.seoUrlTemplates.add(entity);
            });
        },
        isHeadlessRoute(routeName) {
            return HEADLESS_SEO_URL_ROUTES.some((route) => route.routeName === routeName);
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
            // Headless store-api routes reuse the existing storefront route labels (they describe the same entity).
            const headlessRoute = HEADLESS_SEO_URL_ROUTES.find((route) => route.routeName === seoUrlTemplate.routeName);
            const labelRouteName = headlessRoute ? headlessRoute.storefrontRouteName : seoUrlTemplate.routeName;

            const routeName = labelRouteName.replace(/\./g, '-');
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

            // Only persist templates that have a value; the empty blueprint entries are not stored.
            // Filter into a new collection instead of removing while iterating (removing during forEach
            // shifts the indices and would skip entries, persisting blank rows).
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
        setErrorMessagesForEntity(entity, value = null) {
            this.errorMessages[entity.id] = value;
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
                    this.setErrorMessagesForEntity(entity, err.response.data.errors[0].detail);

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
            const templates = [];
            this.seoUrlTemplates.forEach((templateEntity) => {
                if (templateEntity.salesChannelId === salesChannelId) {
                    templates.push(templateEntity);
                }
            });

            // Keep a stable field order regardless of the order entities are loaded from / created after saving.
            const orderedRouteNames = [];
            if (this.salesChannelIsHeadless) {
                HEADLESS_SEO_URL_ROUTES.forEach((route) => orderedRouteNames.push(route.routeName));
            } else {
                this.defaultSeoUrlTemplates.forEach((entity) => orderedRouteNames.push(entity.routeName));
            }

            return templates.sort((a, b) => {
                return orderedRouteNames.indexOf(a.routeName) - orderedRouteNames.indexOf(b.routeName);
            });
        },
    },
};
