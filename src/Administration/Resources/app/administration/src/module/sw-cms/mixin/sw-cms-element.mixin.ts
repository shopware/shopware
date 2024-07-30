import type { PropType } from 'vue';
import { defineComponent } from 'vue';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;
const { cloneDeep, merge } = Shopware.Utils.object;

interface Translation {
    languageId: string;
}
interface Entity {
    translations: Translation[];
}

/**
 * @private
 * @package buyers-experience
 */
export default Mixin.register('cms-element', defineComponent({
    inject: ['cmsService'],

    props: {
        element: {
            type: Object as PropType<Record<string, $TSFixMe>>,
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

    data() {
        return {};
    },

    computed: {
        cmsPageState() {
            return Shopware.Store.get('cmsPageState');
        },

        cmsElements(): Record<string, { defaultConfig: unknown }> {
            return this.cmsService.getCmsElementRegistry() as Record<string, { defaultConfig: unknown }>;
        },

        category(): EntitySchema.Entities['category'] {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
            return Shopware.State.get('swCategoryDetail')?.category as EntitySchema.Entities['category'];
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
                // @ts-expect-error
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                fallbackCategoryConfig = this.getDefaultTranslations(this.category)?.slotConfig?.[this.element.id];
            }

            // eslint-disable-next-line vue/no-mutating-props,@typescript-eslint/no-unsafe-assignment
            this.element.config = merge(
                cloneDeep(defaultConfig),
                // eslint-disable-next-line @typescript-eslint/no-unsafe-member-access
                this.element?.translated?.config || {},
                fallbackCategoryConfig || {},
                this.element?.config || {},
            );
        },

        initElementData(elementName: string) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-argument
            if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                return;
            }

            const elementConfig = this.cmsElements[elementName];
            // @ts-expect-error
            // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
            const defaultData = elementConfig.defaultData ?? {};
            // eslint-disable-next-line vue/no-mutating-props,@typescript-eslint/no-unsafe-assignment
            this.element.data = merge(cloneDeep(defaultData), this.element.data || {});
        },

        getDemoValue(mappingPath: string) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath,
            );
        },

        getDefaultTranslations(entity: Entity) {
            return entity.translations.find((translation) => {
                return translation.languageId === Shopware.Context.api.systemLanguageId;
            });
        },
    },
}));
