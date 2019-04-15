import { Mixin, State } from 'src/core/shopware';

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
            const elementConfig = this.cmsElements[elementName];

            if (!this.element.config || this.element.config === null || !Object.keys(this.element.config).length) {
                this.element.config = elementConfig.defaultConfig || {};
            }
        },

        getDemoValue(mappingPath) {
            return this.cmsService.getPropertyByMappingPath(this.cmsPageState.currentDemoEntity, mappingPath);
        }
    }
});
