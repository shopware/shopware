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

    data() {
        return {};
    },

    computed: {
        cmsPageState() {
            return Shopware.State.get('cmsPageState');
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },

        category() {
            return Shopware.State.get('swCategoryDetail')?.category;
        },
    },

    methods: {
        initElementConfig(elementName) {
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

        initElementData(elementName) {
            if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                return;
            }

            const elementConfig = this.cmsElements[elementName];
            const defaultData = elementConfig.defaultData ?? {};
            // eslint-disable-next-line vue/no-mutating-props
            this.element.data = merge(cloneDeep(defaultData), this.element.data || {});
        },

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath,
            );
        },

        getDefaultTranslations(entity) {
            return entity.translations.find((translation) => {
                return translation.languageId === Shopware.Context.api.systemLanguageId;
            });
        },
    },
}));
