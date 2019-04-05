import { Component, Mixin } from 'src/core/shopware';
import Criteria from 'src/core/data-new/criteria.data';
import utils from 'src/core/service/util.service';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-seo-url-template-card.html.twig';
import './sw-seo-url-template-card.scss';

Component.register('sw-seo-url-template-card', {
    template,

    inject: ['seoUrlTemplateService', 'repositoryFactory', 'context'],

    mixins: [Mixin.getByName('notification')],


    data() {
        return {
            defaultSeoUrlTemplates: null,
            seoUrlTemplates: null,
            isLoading: true,
            seoSettingsComponent: null,
            debouncedPreviews: {},
            previewLoadingStates: {},
            errorMessages: {},
            previews: {},
            variableStores: {},
            seoUrlRepository: {}
        };
    },

    computed: {
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.seoUrlRepository = this.repositoryFactory.create('seo_url_template');

            this.fetchSeoUrlTemplates();

            this.findSettingsDetailComponent();

            this.registerListener();
        },
        fetchSeoUrlTemplates(salesChannelId = null) {
            const criteria = new Criteria();

            if (!salesChannelId) {
                salesChannelId = null;
            }
            criteria.addFilter(Criteria.equals('salesChannelId', salesChannelId));

            this.isLoading = true;

            this.seoUrlRepository.search(criteria, this.context).then((res) => {
                this.seoUrlTemplates = res;
                if (!salesChannelId) {
                    this.defaultSeoUrlTemplates = this.seoUrlTemplates;
                } else {
                    this.createSeoUrlTemplatesFromDefaultRoutes(salesChannelId);
                }
                this.isLoading = false;

                this.seoUrlTemplates.forEach(urlTemplate => {
                    this.fetchSeoUrlPreview(urlTemplate);
                    this.seoUrlTemplateService.getContext(urlTemplate).then(data => {
                        this.createVariablesStore(urlTemplate.id, data);
                    });
                });
            });
        },
        createSeoUrlTemplatesFromDefaultRoutes(salesChannelId) {
            Object.keys(this.defaultSeoUrlTemplates).forEach(defaultId => {
                const defaultEntity = this.defaultSeoUrlTemplates[defaultId];

                const foundId = Object.keys(this.seoUrlTemplates).find(id => {
                    return this.seoUrlTemplates[id].routeName === defaultEntity.routeName;
                });

                if (!foundId) {
                    const entity = this.seoUrlRepository.create(this.context);
                    entity.routeName = defaultEntity.routeName;
                    entity.salesChannelId = salesChannelId;
                    entity.entityName = defaultEntity.entityName;
                    this.seoUrlTemplates.add(entity);
                }
            });
        },
        createVariablesStore(id, data) {
            if (!this.variableStores.hasOwnProperty(id)) {
                const storeOptions = [];

                Object.keys(data).forEach((property) => {
                    storeOptions.push({ id: property, name: `${property}` });
                });

                this.$set(this.variableStores, id, new LocalStore(storeOptions));
            }
        },
        getVariablesStore(id) {
            if (this.variableStores.hasOwnProperty(id)) {
                return this.variableStores[id];
            }
            return false;
        },
        findSettingsDetailComponent() {
            this.seoSettingsComponent = this.$parent;
            while (this.seoSettingsComponent.$options.name !== 'sw-settings-seo') {
                this.seoSettingsComponent = this.seoSettingsComponent.$parent;
            }
        },
        registerListener() {
            this.seoSettingsComponent.$on('saved', this.onSave);
        },
        getLabel(seoUrlTemplate) {
            const routeName = seoUrlTemplate.routeName.replace(/\./g, '-');
            if (this.$te(`sw-seo-url-template-card.routeNames.${routeName}`)) {
                return this.$tc(`sw-seo-url-template-card.routeNames.${routeName}`);
            }

            return seoUrlTemplate.routeName;
        },
        getPlaceholder(seoUrlTemplate) {
            if (!seoUrlTemplate.salesChannelId) {
                return '';
            }

            const defaultEntityId = Object.keys(this.defaultSeoUrlTemplates).find(id => {
                return this.defaultSeoUrlTemplates[id].routeName === seoUrlTemplate.routeName;
            });

            return this.defaultSeoUrlTemplates[defaultEntityId].template;
        },
        onSave() {
            const hasError = Object.keys(this.errorMessages).some((key) => {
                return this.errorMessages[key] !== '';
            });

            if (hasError) {
                this.createSaveErrorNotification();
                return;
            }

            this.seoUrlRepository.sync(this.seoUrlTemplates, this.context);

            this.createSaveSuccessNotification();
        },
        createSaveErrorNotification() {
            const titleSaveSuccess = this.$tc('sw-seo-url-template-card.general.titleSaveError');
            const messageSaveSuccess = this.$tc('sw-seo-url-template-card.general.messageSaveError');

            this.createNotificationError({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },
        createSaveSuccessNotification() {
            const titleSaveSuccess = this.$tc('sw-seo-url-template-card.general.titleSaveSuccess');
            const messageSaveSuccess = this.$tc('sw-seo-url-template-card.general.messageSaveSuccess');

            this.createNotificationSuccess({
                title: titleSaveSuccess,
                message: messageSaveSuccess
            });
        },

        onSelectInput(propertyName, entity) {
            const templateValue = entity.template ? (`${entity.template}/`) : '';
            entity.template = `${templateValue}{{ ${propertyName} }}`;
            this.fetchSeoUrlPreview(entity);

            const selectComponent = this.$refs[`select-${entity.id}`][0];
            selectComponent.loadSelected();
        },
        onInput(entity) {
            this.debouncedPreviewSeoUrlTemplate(entity);
        },
        debouncedPreviewSeoUrlTemplate(entity) {
            if (!this.debouncedPreviews[entity.id]) {
                this.debouncedPreviews[entity.id] = utils.debounce(() => {
                    this.fetchSeoUrlPreview(entity);
                }, 400);
            } else {
                this.errorMessages[entity.id] = '';
            }

            this.debouncedPreviews[entity.id]();
        },
        fetchSeoUrlPreview(entity) {
            this.$set(this.previewLoadingStates, entity.id, true);
            this.seoUrlTemplateService.preview(entity).then((response) => {
                this.$set(this.previews, entity.id, response);
                if (response.length < 1) {
                    this.$set(
                        this.errorMessages,
                        entity.id,
                        this.$tc('sw-seo-url-template-card.general.tooltipInvalidTemplate')
                    );
                } else {
                    this.$set(this.errorMessages, entity.id, '');
                }
                this.previewLoadingStates[entity.id] = false;
            }).catch(err => {
                this.$set(this.errorMessages, entity.id, err.response.data.errors[0].detail);
                this.$set(this.previews, entity.id, []);
                this.previewLoadingStates[entity.id] = false;
            });
        },
        abortOnSalesChannelChange() {
            return Object.keys(this.seoUrlTemplates).some(id => {
                if (this.seoUrlTemplates[id].isNew() && !this.seoUrlTemplates[id].template) {
                    return false;
                }
                return this.seoUrlRepository.hasChanges(this.seoUrlTemplates[id]);
            });
        },
        saveOnSalesChannelChange() {
            return this.onSave();
        },
        onSalesChannelChanged(salesChannelId) {
            this.fetchSeoUrlTemplates(salesChannelId);
            this.errorMessages = {};
            this.previewLoadingStates = {};
            this.debouncedPreviews = {};
            this.previews = {};
        }
    }
});
