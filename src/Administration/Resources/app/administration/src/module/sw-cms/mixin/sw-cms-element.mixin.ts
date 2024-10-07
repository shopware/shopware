import { defineComponent, type PropType } from 'vue';
import { type RuntimeSlot } from '../service/cms.service';

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
                defaultConfig = elementConfig?.defaultConfig || {};
            }

            let fallbackCategoryConfig = {};
            if (this.category?.translations) {
                // @ts-expect-error
                // eslint-disable-next-line max-len
                // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment,@typescript-eslint/no-unsafe-member-access
                fallbackCategoryConfig = this.getDefaultTranslations(this.category)?.slotConfig?.[this.element.id];
            }

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
            const defaultData = elementConfig?.defaultData ?? {};
            this.element.data = merge(cloneDeep(defaultData), this.element.data || {});
        },

        getDemoValue(mappingPath: string) {
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
