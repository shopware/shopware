import { defineComponent } from 'vue';
import { type RuntimeSlot } from '../service/cms.service';
import './sw-cms-state.mixin';

const { Mixin } = Shopware;
const { types } = Shopware.Utils;
const { cloneDeep, merge, get, set, has } = Shopware.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export default Mixin.register(
    'cms-element',
    defineComponent({
        inject: ['cmsService'],

        mixins: [
            Mixin.getByName('cms-state'),
        ],

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
        },
        methods: {
            initElementConfig() {
                this.initBaseConfig();
                this.applyContentOverride();
            },

            initBaseConfig() {
                if (!this.element.type) {
                    return;
                }

                const config = merge({}, this.cmsElements[this.element.type]?.defaultConfig, this.defaultConfig);

                if (!this.element.config) {
                    set(this.element, 'config', {});
                }

                Object.entries(config).forEach(
                    ([
                        key,
                        value,
                    ]) => {
                        const path = `config.${key}`;

                        if (has(this.element, path)) {
                            return;
                        }

                        const newValue: unknown = get(this.element, `translated.${path}`, value);

                        set(this.element, path, newValue);
                    },
                );
            },

            applyContentOverride() {
                if (!this.contentEntity || !this.contentEntity.slotConfig || !this.element.id) {
                    return;
                }

                const overrideConfig = this.contentEntity.slotConfig[this.element.id];

                if (!overrideConfig) {
                    return;
                }

                Object.entries(overrideConfig).forEach(
                    ([
                        key,
                        value,
                    ]) => {
                        set(this.element, `config.${key}`, value);
                    },
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
        },
    }),
);
