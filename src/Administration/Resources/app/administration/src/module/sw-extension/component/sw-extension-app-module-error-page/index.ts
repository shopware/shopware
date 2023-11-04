import template from './sw-extension-app-module-error-page.html.twig';
import './sw-extension-app-module-error-page.scss';

/**
 * @package merchant-services
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    methods: {
        goBack(): void {
            this.$router.go(-1);
        },
    },
});
