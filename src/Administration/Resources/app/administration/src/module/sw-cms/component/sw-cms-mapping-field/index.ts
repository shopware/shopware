import { type PropType } from 'vue';
import template from './sw-cms-mapping-field.html.twig';
import './sw-cms-mapping-field.scss';

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['cmsService'],

    props: {
        config: {
            type: Object as PropType<{
                source: 'static' | 'mapped' | 'default';
                value: unknown;
                [key: string]: unknown;
            }>,
            required: true,
            default() {
                return {
                    source: 'static',
                    value: null,
                };
            },
        },

        valueTypes: {
            type: [String, Array],
            required: false,
            default: 'string',
        },

        entity: {
            type: String,
            required: false,
            default: null,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },
    },

    data() {
        return {
            mappingTypes: {} as unknown,
            allowedMappingTypes: [] as string[],
            demoValue: null as unknown,
        };
    },

    computed: {
        isMapped() {
            return this.config.source === 'mapped';
        },

        hasPreview() {
            if (this.isCompatEnabled('INSTANCE_SCOPED_SLOTS')) {
                return typeof this.$scopedSlots.preview !== 'undefined';
            }

            return this.$slots.preview !== undefined;
        },

        cmsPageState() {
            return Shopware.Store.get('cmsPage');
        },
    },

    watch: {
        cmsPageState: {
            deep: true,
            handler() {
                this.updateMappingTypes();
                this.updateDemoValue();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateMappingTypes();
            this.updateDemoValue();
        },

        updateMappingTypes() {
            this.mappingTypes = this.cmsPageState.currentMappingTypes;
            this.getAllowedMappingTypes();

            if (this.config.source !== 'mapped') {
                return;
            }

            const mappingPath = (this.config.value as string).split('.');

            if (mappingPath[0] !== this.cmsPageState.currentMappingEntity) {
                this.onMappingRemove();
            }
        },

        updateDemoValue() {
            if (this.config.source !== 'mapped') {
                return;
            }

            this.demoValue = this.getDemoValue(this.config.value as string);
        },

        onMappingSelect(property: string) {
            this.config.source = 'mapped';
            this.config.value = property;
            this.demoValue = this.getDemoValue(property);
        },

        onMappingRemove() {
            this.config.source = 'static';
            this.config.value = this.config.type === Array ? [] : null;
            this.demoValue = null;
        },

        getAllowedMappingTypes() {
            let types: string[] = [];

            if (this.valueTypes === 'entity') {
                const mappingTypes = this.mappingTypes as {
                    entity: {
                        [key: string]: string[]
                    };
                };

                if (
                    this.entity !== null &&
                    mappingTypes.entity &&
                    mappingTypes.entity[this.entity]
                ) {
                    types = mappingTypes.entity[this.entity];
                }
            } else {
                const mappingTypes = this.mappingTypes as {
                    [key: string]: string[]
                };

                Object.keys(mappingTypes).forEach((type) => {
                    if (type === this.valueTypes || this.valueTypes.includes(type)) {
                        types = [...types, ...mappingTypes[type]];
                        types.sort();
                    }
                });
            }

            this.allowedMappingTypes = types;
        },

        getDemoValue(mappingPath: string): unknown {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath,
            );
        },
    },
});
