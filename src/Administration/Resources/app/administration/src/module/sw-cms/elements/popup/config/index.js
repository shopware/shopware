/**
 * @private
 * @sw-package discovery
 */
import template from './sw-cms-el-config-popup.html.twig';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-config-popup', {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element')
    ],

    data() {
        return {
            triggerOptions: [
                { 
                    label: this.$tc('sw-cms.elements.popup.config.options.click'), 
                    value: 'click' 
                },
                { 
                    label: this.$tc('sw-cms.elements.popup.config.options.hover'), 
                    value: 'hover' 
                },
                { 
                    label: this.$tc('sw-cms.elements.popup.config.options.auto'), 
                    value: 'auto' 
                }
            ]
        };
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('popup-element');
        }
    }
});