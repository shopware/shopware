import { types } from '../../../core/service/util.service';

const { Mixin } = Shopware;
const { cloneDeep } = Shopware.Utils.object;

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

            this.element.config = Object.assign(cloneDeep(defaultConfig), this.element.config || {});
        },

        initElementData(elementName) {
            if (types.isPlainObject(this.element.data) && Object.keys(this.element.data).length > 0) {
                return;
            }

            const elementConfig = this.cmsElements[elementName];
            const defaultData = elementConfig.defaultData ? elementConfig.defaultData : {};

            this.element.data = Object.assign(cloneDeep(defaultData), this.element.data || {});
        },

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(
                this.cmsPageState.currentDemoEntity,
                mappingPath
            );
        }
    }
});
