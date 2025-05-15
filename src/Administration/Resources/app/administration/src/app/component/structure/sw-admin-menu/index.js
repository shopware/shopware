import template from './sw-admin-menu.html.twig';
import './sw-admin-menu.scss';
import { MtText } from '@shopware-ag/meteor-component-library';

/**
 * @sw-package framework
 *
 * @private
 */
export default {
    template,

    components: {
        MtText,
    },

    data() {
        return {
            isDarkMode: false
        };
    },

    watch: {
        isDarkMode: {
            handler(newValue) {
                if (newValue)  {
                    document.documentElement.dataset.theme = 'dark';
                } else {
                    document.documentElement.dataset.theme = 'light';
                }
            },
            immediate: true
        }
    }
};
