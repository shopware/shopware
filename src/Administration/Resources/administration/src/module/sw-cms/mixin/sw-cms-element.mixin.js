import { Mixin, State } from 'src/core/shopware';
import { cloneDeep } from 'src/core/service/utils/object.utils';

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

    computed: {
        cmsPageState() {
            return State.getStore('cmsPageState');
        },

        cmsElements() {
            return this.cmsService.getCmsElementRegistry();
        }
    },

    methods: {
        initElementConfig(elementName) {
            let defaultConfig = this.defaultConfig;

            if (!defaultConfig || defaultConfig === null) {
                const elementConfig = this.cmsElements[elementName];
                defaultConfig = elementConfig.defaultConfig;
            }

            this.element.config = Object.assign(cloneDeep(defaultConfig), this.element.config || {});
        },

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(this.cmsPageState.currentDemoEntity, mappingPath);
        }
    }
});
