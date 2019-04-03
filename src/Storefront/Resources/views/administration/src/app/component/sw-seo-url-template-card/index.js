import { Component, State, Mixin } from 'src/core/shopware';
import utils from 'src/core/service/util.service';
import LocalStore from 'src/core/data/LocalStore';
import template from './sw-seo-url-template-card.html.twig';
import './sw-seo-url-template-card.scss';

Component.register('sw-seo-url-template-card', {
    template,

    inject: ['seoUrlTemplateService'],

    mixins: [Mixin.getByName('notification')],


    data() {
        return {
            seoUrlTemplates: null,
            isLoading: true,
            seoSettingsComponent: null,
            debouncedPreviews: {},
            previewLoadingStates: {},
            errorMessages: {},
            previews: {},
            variableStores: {}
        };
    },

    computed: {
        seoUrlTemplateStore() {
            return State.getStore('seo_url_template');
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.findSettingsDetailComponent();

            this.registerListener();

            this.seoUrlTemplateStore.getList().then((res) => {
                this.seoUrlTemplates = res.items;
                this.isLoading = false;

                this.seoUrlTemplates.forEach(urlTemplate => {
                    this.fetchSeoUrlPreview(urlTemplate);
                    this.seoUrlTemplateService.getContext(urlTemplate).then(data => {
                        this.createVariablesStore(urlTemplate.routeName, data);
                    });
                });
            });
        },
        createVariablesStore(routeName, data) {
            if (!this.variableStores.hasOwnProperty(routeName)) {
                const storeOptions = [];

                Object.keys(data).forEach((property) => {
                    storeOptions.push({ id: property, name: `${property}` });
                });

                this.variableStores = Object.assign(
                    {},
                    this.variableStores,
                    { [routeName]: new LocalStore(storeOptions) }
                );
            }
        },
        getVariablesStore(routeName) {
            if (this.variableStores.hasOwnProperty(routeName)) {
                return this.variableStores[routeName];
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
        getLabel(seoUrl) {
            const routeName = seoUrl.routeName.replace(/\./g, '-');
            if (this.$te(`sw-seo-url-template-card.routeNames.${routeName}`)) {
                return this.$tc(`sw-seo-url-template-card.routeNames.${routeName}`);
            }

            return seoUrl.routeName;
        },
        onSave() {
            const hasError = Object.keys(this.errorMessages).some((key) => {
                return this.errorMessages[key] !== '';
            });

            if (hasError) {
                this.createSaveErrorNotification();
                return;
            }

            this.seoUrlTemplateStore.sync();
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
        }
    }
});
