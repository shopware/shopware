import template from './sw-extension-app-module-error-page.html.twig';
import './sw-extension-app-module-error-page.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package checkout
 * @private
 */
export default wrapComponentConfig({
    template,

    methods: {
        goBack(): void {
            this.$router.go(-1);
        },
    },
});
