import { defineComponent } from 'vue';
import { type EnrichedSlotData, type RuntimeSlot, type CmsSlotConfig } from '../service/cms.service';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;
const { cloneDeep, merge } = Shopware.Utils.object;

interface Translation {
    languageId: string;
}

interface TranslationWithSlotConfig extends Translation {
    slotConfig?: {
        [slotId: string]: CmsSlotConfig;
    };
}

interface Entity {
    translations: Translation[];
    translated?: TranslationWithSlotConfig;
}

/**
 * @private
 * @sw-package discovery
 */
export default Mixin.register(
    'cms-element',
    defineComponent({
        inject: ['cmsService'],

        props: {
            element: {
                type: Object as PropType<RuntimeSlot>,
                required: true,
            },

            defaultConfig: {
                type: Object,
                required: false,
                default: null,
            },

            disabled: {
                type: Boolean,
                required: false,
                default: false,
            },
        },

        computed: {
            cmsPageState() {
                return Shopware.Store.get('cmsPage');
            },

            cmsElements() {
                return this.cmsService.getCmsElementRegistry();
            },

            category(): Entity | null {
                try {
                    return Shopware.Store.get('swCategoryDetail')?.category as Entity;
                } catch {
                    return null;
                }
            },

            product(): Entity | null {
                try {
                    return Shopware.Store.get('swProductDetail')?.product as Entity;
                } catch {
                    return null;
                }
            },

            configOverride(): EnrichedSlotData {
                return (
                    this.getEntitySlotConfig() ??
                    this.element?.translated?.config ??
                    this.element?.config ??
                    {}
                ) as EnrichedSlotData;
            },
        },

        methods: {
            initElementConfig(elementName: string) {
                let defaultConfig = this.defaultConfig;

                if (!defaultConfig) {
                    const elementConfig = this.cmsElements[elementName];
                    defaultConfig = elementConfig?.defaultConfig || {};
                }

                this.element.config = merge(
                    cloneDeep(defaultConfig),
                    this.configOverride,
                );
            },

            initElementData(elementName: string) {
                if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                    return;
                }

                const elementConfig = this.cmsElements[elementName];
                const defaultData = elementConfig?.defaultData ?? {};
                this.element.data = merge(cloneDeep(defaultData), this.element.data || {});
            },

            getDemoValue(mappingPath: string) {
                return this.cmsService.getPropertyByMappingPath(this.cmsPageState.currentDemoEntity, mappingPath);
            },

            getEntitySlotConfig() {
                const entity = this.category ?? this.product;

                if (!entity) {
                    return null;
                }

                const translation = (
                    entity.translated ??
                    this.getDefaultTranslations(entity)
                ) as TranslationWithSlotConfig;

                return translation?.slotConfig?.[this.element.id] ?? null;
            },

            getDefaultTranslations(entity: Entity) {
                return entity.translations?.find((translation) => {
                    return translation.languageId === Shopware.Context.api.systemLanguageId;
                });
            },
        },
    }),
);
