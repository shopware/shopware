const { Mixin } = Shopware;
const { cloneDeep, merge } = Shopware.Utils.object;

Mixin.register('cms-element', {
    inject: ['cmsService'],

    model: {
        prop: 'element',
        event: 'element-update'
    },

    props: {
        element: {
            type: Object,
            required: true
        },

        defaultConfig: {
            type: Object,
            required: false,
            default: null
        }
    },

    data() {
        return {
            cmsPageState: this.$store.state.cmsPageState
        };
    },

    computed: {
        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        }
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

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath
            );
        }
    }
});
