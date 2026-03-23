import template from './sw-cms-mapping-field.html.twig';
import './sw-cms-mapping-field.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    inject: [
        'cmsService',
        'repositoryFactory',
    ],

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
            type: [
                String,
                Array,
            ],
            required: false,
            default: 'string',
        },

        entity: {
            type: String as PropType<Extract<keyof EntitySchema.Entities, string> | null>,
            required: false,
            default: null,
        },

        label: {
            type: String,
            required: false,
            default: '',
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    data() {
        return {
            mappingTypes: {} as unknown,
            allowedMappingTypes: [] as string[],
            demoValue: null as unknown,
            demoValueFetchId: 0,
        };
    },

    computed: {
        isMapped() {
            return this.config.source === 'mapped';
        },

        hasPreview() {
            return this.$slots.preview !== undefined;
        },

        cmsPageState() {
            return Shopware.Store.get('cmsPage');
        },
    },

    watch: {
        'cmsPageState.currentMappingTypes': {
            handler() {
                this.updateMappingTypes();
            },
        },

        'cmsPageState.currentMappingEntity': {
            handler() {
                this.updateMappingTypes();
            },
        },

        'cmsPageState.currentDemoEntity': {
            handler() {
                void this.updateDemoValue();
            },
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.updateMappingTypes();
            void this.updateDemoValue();
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

        async updateDemoValue() {
            if (this.config.source !== 'mapped') {
                this.demoValueFetchId += 1;
                this.demoValue = null;

                return;
            }

            const fetchId = this.demoValueFetchId + 1;
            this.demoValueFetchId = fetchId;

            const demoValue = this.getDemoValue(this.config.value as string);
            this.demoValue = demoValue;

            if (this.valueTypes !== 'entity' || this.entity === null || typeof demoValue !== 'string') {
                return;
            }

            try {
                const entity = await this.repositoryFactory.create(this.entity).get(demoValue, Shopware.Context.api);

                if (fetchId !== this.demoValueFetchId || !entity) {
                    return;
                }

                this.demoValue = entity;
            } catch {
                if (fetchId === this.demoValueFetchId) {
                    this.demoValue = demoValue;
                }
            }
        },

        onMappingSelect(property: string) {
            this.config.source = 'mapped';
            this.config.value = property;
            void this.updateDemoValue();
        },

        onMappingRemove() {
            this.demoValueFetchId += 1;
            this.config.source = 'static';
            this.config.value = this.config.type === Array ? [] : null;
            this.demoValue = null;
        },

        getAllowedMappingTypes() {
            let types: string[] = [];

            if (this.valueTypes === 'entity') {
                const mappingTypes = this.mappingTypes as {
                    entity: {
                        [key: string]: string[];
                    };
                };

                if (this.entity !== null && mappingTypes.entity && mappingTypes.entity[this.entity]) {
                    types = mappingTypes.entity[this.entity];
                }
            } else {
                const mappingTypes = this.mappingTypes as {
                    [key: string]: string[];
                };

                Object.keys(mappingTypes).forEach((type) => {
                    if (type === this.valueTypes || this.valueTypes.includes(type)) {
                        types = [
                            ...types,
                            ...mappingTypes[type],
                        ];
                        types.sort();
                    }
                });
            }

            this.allowedMappingTypes = types;
        },

        getDemoValue(mappingPath: string): unknown {
            return this.cmsService.getPropertyByMappingPath(this.cmsPageState.currentDemoEntity, mappingPath);
        },
    },
});
