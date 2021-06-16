const { Mixin } = Shopware;
const { types } = Shopware.Utils;
const { cloneDeep, merge } = Shopware.Utils.object;

Mixin.register('cms-element', {
    inject: ['cmsService'],

    model: {
        prop: 'element',
        event: 'element-update',
    },

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
        return {
            cmsPageState: Shopware.State.get('cmsPageState'),
        };
    },

    computed: {
        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        },
    },

    methods: {
        initElementConfig(elementName) {
            let defaultConfig = this.defaultConfig;

            if (!defaultConfig || defaultConfig === null) {
                const elementConfig = this.cmsElements[elementName];
                defaultConfig = elementConfig.defaultConfig || {};
            }

            this.element.config = merge(cloneDeep(defaultConfig), this.element.config || {});
        },

        initElementData(elementName) {
            if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                const elemData = cloneDeep(this.element.data);
                this.$set(this.element, 'data', elemData);

                return;
            }

            const elementConfig = this.cmsElements[elementName];
            const defaultData = elementConfig.defaultData ? elementConfig.defaultData : {};

            const elemData = merge(cloneDeep(defaultData), this.element.data || {});

            this.$set(this.element, 'data', elemData);
        },

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath,
            );
        },
    },
});
