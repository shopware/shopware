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

        currentSalesChannel() {
            return this.salesChannels.find((entity) => {
                return entity.id === this.salesChannelId;
            });
        },

        salesChannelIsHeadless() {
            if (!this.currentSalesChannel) {
                return false;
            }

            return this.currentSalesChannel.typeId === Defaults.apiSalesChannelTypeId;
        },

        headlessSalesChannelHasExternalDomain() {
            if (!this.salesChannelIsHeadless) {
                return false;
            }

            return !!this.currentSalesChannel?.domains?.find((domain) => domain.isExternalStorefront);
        },

        salesChannelSupportsSeoUrlTemplates() {
            if (!this.currentSalesChannel) {
                return true;
            }

            const supported = [
                Defaults.storefrontSalesChannelTypeId,
                Defaults.apiSalesChannelTypeId,
            ];
            if (!supported.includes(this.currentSalesChannel.typeId)) {
                return false;
            }

            return !this.salesChannelIsHeadless || this.headlessSalesChannelHasExternalDomain;
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
                    if (!this.seoUrlTemplates.has(entity.id) && (salesChannelId || !entity.isHeadless)) {
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
            // Only the default template family matching the sales channel type is relevant: headless channels
            // use the store-api routes, storefront channels the frontend routes.
            const relevantDefaults = this.defaultSeoUrlTemplates.filter(
                (defaultEntity) => Boolean(defaultEntity.isHeadless) === this.salesChannelIsHeadless,
            );

            // Iterate over the default seo url templates and create new entities for the actual sales channel
            // if they do not exist
            relevantDefaults.forEach((defaultEntity) => {
                const entityAlreadyExists = this.seoUrlTemplates.some((entity) => {
                    return entity.routeName === defaultEntity.routeName && entity.salesChannelId === salesChannelId;
                });

                if (!entityAlreadyExists) {
                    const entity = this.seoUrlTemplateRepository.create();
                    entity.routeName = defaultEntity.routeName;
                    entity.salesChannelId = salesChannelId;
                    entity.entityName = defaultEntity.entityName;
                    entity.isHeadless = defaultEntity.isHeadless;
                    entity.template = null;
                    this.seoUrlTemplates.add(entity);
                }
            });
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
            // Default rows ("All Sales Channels") have no value to inherit from.
            if (!seoUrlTemplate.salesChannelId) {
                return null;
            }

            const defaultEntity = this.defaultSeoUrlTemplates.find((entity) => {
                return entity.routeName === seoUrlTemplate.routeName;
            });

            return defaultEntity ? defaultEntity.template : null;
        },
        onClickSave() {
            const hasError = Object.keys(this.errorMessages).some((key) => {
                return this.errorMessages[key] !== null;
            });

            if (hasError) {
                this.createSaveErrorNotification();
                return;
            }

            const templatesToSync = this.seoUrlTemplates;

            // On "All Sales Channels" the storefront and headless default templates are kept in sync: applying
            // the edited storefront value to the headless counterpart (matched by entity) so both route
            // families share the same relative template.
            if (!this.salesChannelId) {
                templatesToSync.forEach((entry) => {
                    const headlessDefault = this.defaultSeoUrlTemplates.find((defaultEntity) => {
                        return defaultEntity.isHeadless && defaultEntity.entityName === entry.entityName;
                    });

                    if (!headlessDefault) {
                        return;
                    }

                    headlessDefault.template = entry.template;
                    headlessDefault.getOrigin().template = entry.getOrigin().template;
                    if (!templatesToSync.some((entity) => entity.id === headlessDefault.id)) {
                        templatesToSync.push(headlessDefault);
                    }
                });
            }

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
                }, 800);
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
            const criteria = new Criteria(1, 25);
            criteria.addAssociation('domains').addSorting(Criteria.sort('domains.isExternalStorefront', 'DESC'));

            this.salesChannelRepository.search(criteria).then((response) => {
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
