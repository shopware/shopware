/* eslint-disable @typescript-eslint/no-unsafe-argument */
/* eslint-disable @typescript-eslint/no-unsafe-member-access */
/* eslint-disable @typescript-eslint/no-unsafe-assignment */
import { defineComponent } from 'vue';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;
const { cloneDeep, merge } = Shopware.Utils.object;

/**
 * @private
 * @package buyers-experience
 */
export default Mixin.register('cms-element', defineComponent({
    inject: ['cmsService'],

    props: {
        element: {
            type: Object,
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
        cmsElements(): Record<string, $TSFixMe> {
            return this.cmsService.getCmsElementRegistry() as Record<string, $TSFixMe>;
        },

        category(): undefined|EntitySchema.Entities['category'] {
            // eslint-disable-next-line max-len, @typescript-eslint/no-unsafe-member-access
            return Shopware.State.get('swCategoryDetail')?.category as unknown as undefined|EntitySchema.Entities['category'];
        },

        cmsPageState() {
            return Shopware.Store.get('cmsPageState');
        },
    },

    methods: {
        initElementConfig(elementName: string) {
            let defaultConfig = this.defaultConfig;
            if (!defaultConfig) {
                const elementConfig = this.cmsElements[elementName];
                defaultConfig = elementConfig.defaultConfig || {};
            }

            let fallbackCategoryConfig = {};
            if (this.category?.translations) {
                fallbackCategoryConfig = this.getDefaultTranslations(this.category)?.slotConfig?.[this.element.id];
            }

            // eslint-disable-next-line vue/no-mutating-props
            this.element.config = merge(
                cloneDeep(defaultConfig),
                this.element?.translated?.config || {},
                fallbackCategoryConfig || {},
                this.element?.config || {},
            );
        },

        initElementData(elementName: string) {
            if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                return;
            }

            const elementConfig = this.cmsElements[elementName];
            const defaultData = elementConfig.defaultData ?? {};
            // eslint-disable-next-line vue/no-mutating-props
            this.element.data = merge(cloneDeep(defaultData), this.element.data || {});
        },

        getDemoValue(mappingPath: string): $TSFixMe {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath,
            );
        },

        getDefaultTranslations(entity: EntitySchema.Entities['category']): $TSFixMe {
            return entity.translations.find((translation: $TSFixMe) => {
                return translation.languageId === Shopware.Context.api.systemLanguageId;
            });
        },
    },
}));
