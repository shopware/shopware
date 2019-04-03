import { Mixin } from 'src/core/shopware';
import cmsService from 'src/module/sw-cms/service/cms.service';
import cmsPageState from 'src/module/sw-cms/state/cms-page.state';

Mixin.register('cms-element', {
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
            return cmsPageState;
        },

        cmsElements() {
            return cmsService.getCmsElementRegistry();
        }
    },

    methods: {
        initElementConfig(elementName) {
            const elementConfig = this.cmsElements[elementName];

            if (!this.element.config || this.element.config === null || !Object.keys(this.element.config).length) {
                this.element.config = elementConfig.defaultConfig || {};
            }
        }
    }
});
