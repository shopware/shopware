/**
 * @private
 * @sw-package discovery
 */
import template from './sw-cms-el-popup.html.twig';
import '../sw-cms-el-popup.scss';

/**
 * @private
 * @sw-package discovery
 */
Shopware.Component.register('sw-cms-el-popup', {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element')
    ],
    
    inject: ['cmsService'],
    
    data() {
        return {
            showPopup: false
        };
    },

    computed: {
        popupStyles() {
            return {
                width: this.element.config.width.value,
                height: this.element.config.height.value
            };
        }
    },

    methods: {
        togglePopup() {
            this.showPopup = !this.showPopup;
        },
        
        closePopup() {
            this.showPopup = false;
        },
        
        createdComponent() {
            // Initialize the CMS element
            this.initElementConfig('popup-element');
            this.initElementData('popup-element');
        }
    },

    created() {
        this.createdComponent();
    }
});