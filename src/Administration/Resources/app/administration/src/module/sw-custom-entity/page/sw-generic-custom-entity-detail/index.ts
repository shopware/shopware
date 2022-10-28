import type {
    AdminTabsDefinition,
    CustomEntityDefinition,
    CustomEntityProperties,
} from 'src/app/service/custom-entity-definition.service';
import type EntityCollection from 'src/core/data/entity-collection.data';
import type Repository from 'src/core/data/repository.data';

import type { Entity } from '@shopware-ag/admin-extension-sdk/es/data/_internals/Entity';
import template from './sw-generic-custom-entity-detail.html.twig';
import './sw-generic-custom-entity-detail.scss';

const { Component, Mixin } = Shopware;

interface CmsSlotOverrides {
    [key: string]: unknown
}

interface CustomEntityData extends Entity {
    swCmsPageId: string | null;
    swSlotConfig: CmsSlotOverrides | null;
}

type GenericCustomEntityDetailData = {
    isLoading: boolean,
    isSaveSuccessful: boolean,
    customEntityName: string,
    entityAccentColor: string,
    customEntityData: CustomEntityData|null,
    customEntityDataDefinition?: CustomEntityDefinition,
    customEntityProperties?: CustomEntityProperties,
    customEntityDataRepository?: Repository,
    customEntityDataInstances?: EntityCollection,
};

/**
 * @private
 */
Component.register('sw-generic-custom-entity-detail', {
    template,

    inject: [
        'customEntityDefinitionService',
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    data(): GenericCustomEntityDetailData {
        return {
            isLoading: false,
            isSaveSuccessful: false,
            customEntityName: '',
            entityAccentColor: '',
            customEntityData: null,
            customEntityDataDefinition: undefined,
            customEntityProperties: undefined,
            customEntityDataRepository: undefined,
            customEntityDataInstances: undefined,
        };
    },

    computed: {
        customEntityDataId(): string|null {
            return this.$route.params?.id;
        },

        detailTabs(): AdminTabsDefinition[] {
            return this.customEntityDataDefinition?.flags['admin-ui']?.detail?.tabs ?? [];
        },

        mainTabName(): string|undefined {
            return this.detailTabs?.[0]?.name;
        },

        titlePropertyName(): string|undefined {
            return this.detailTabs?.[0]?.cards?.[0].fields?.[0].ref;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initializeCustomEntity();
            void this.loadData();
        },

        initializeCustomEntity() {
            const entityName = this.$route.params.entityName;

            const customEntityDataDefinition = this.customEntityDefinitionService.getDefinitionByName(entityName) ?? null;
            if (!customEntityDataDefinition) {
                return;
            }

            this.customEntityName = customEntityDataDefinition.entity;
            this.customEntityProperties = customEntityDataDefinition.properties;

            const adminConfig = customEntityDataDefinition.flags['admin-ui'];
            this.entityAccentColor = adminConfig.color;
            // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            this.$route.meta.$module.icon = adminConfig.icon;

            // ToDo NEXT-22874 - Favicon handling

            this.customEntityDataRepository = this.repositoryFactory.create(customEntityDataDefinition.entity);
            this.customEntityDataDefinition = customEntityDataDefinition;
        },

        loadData(): Promise<void> {
            this.isLoading = true;
            if (!this.customEntityDataRepository) {
                return Promise.reject();
            }

            if (!this.customEntityDataId) {
                this.customEntityData = this.customEntityDataRepository.create(Shopware.Context.api) as CustomEntityData;
                this.isLoading = false;

                return Promise.resolve();
            }

            return this.customEntityDataRepository.get(this.customEntityDataId, Shopware.Context.api).then((data) => {
                this.customEntityData = data as CustomEntityData;
            }).finally(() => {
                this.isLoading = false;
            });
        },

        async onSave(): Promise<void> {
            this.isLoading = true;

            if (!this.customEntityData) {
                return Promise.reject();
            }

            return this.customEntityDataRepository?.save(this.customEntityData).then(() => {
                this.isSaveSuccessful = true;

                if (!this.customEntityDataId && this.customEntityData?.id) {
                    this.$router.push({
                        name: 'sw.custom.entity.detail',
                        params: {
                            id: this.customEntityData.id,
                        },
                    });
                }

                void this.loadData();
            }).finally(() => {
                this.isLoading = false;
            });
        },

        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onChangeLanguage(languageId: string) {
            Shopware.State.commit('context/setApiLanguageId', languageId);
            void this.loadData();
        },

        getFieldTranslation(namespace: string, name: string, suffix = '', checkExistence = false): string {
            const snippetKey = [this.customEntityName, namespace, name].join('.').concat(suffix);
            if (checkExistence && !this.$te(snippetKey)) {
                return '';
            }

            return this.$tc(snippetKey);
        },

        getLabel(namespace: string, name: string): string {
            return this.getFieldTranslation(namespace, name);
        },

        getPlaceholder(namespace: string, name: string): string {
            return this.getFieldTranslation(namespace, name, 'Placeholder', true);
        },

        getHelpText(namespace: string, name: string): string {
            return this.getFieldTranslation(namespace, name, 'HelpText', true);
        },

        getType(field: string): string {
            return this.customEntityProperties?.[field].type || '';
        },

        updateCmsPageId(cmsPageId: string | null) {
            if (!this.customEntityData) {
                return;
            }

            this.customEntityData.swCmsPageId = cmsPageId;
        },

        updateCmsSlotOverwrites(cmsSlotOverwrites: CmsSlotOverrides | null) {
            if (!this.customEntityData) {
                return;
            }

            this.customEntityData.swSlotConfig = cmsSlotOverwrites;
        },

        onCreateLayout() {
            if (!this.customEntityData) {
                return;
            }

            this.$router.push({
                name: 'sw.cms.create',
                params: {
                    id: this.customEntityData.id,
                    type: this.customEntityName,
                },
            });
        },
    },
});
