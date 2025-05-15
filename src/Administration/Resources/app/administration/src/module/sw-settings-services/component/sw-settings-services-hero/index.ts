/**
 * @sw-package framework
 */

import template from './sw-settings-services-hero.html.twig'
import './sw-settings-services-hero.scss';
import imageEditor from './assets/image-editor.png';
import previewGenerator from './assets/3d-preview-generator.png';
import copilot from './assets/copilot.png';
import swSettingsServicesFramedIcon from '../sw-settings-services-framed-icon';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-hero',

    template,

    components: {
        swSettingsServicesFramedIcon,
    },

    props: {
        feedbackLink: {
            type: String,
            required: true,
        },
        documentationLink: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            assets: {
                imageEditor,
                previewGenerator,
                copilot,
            },
        };
    },

    computed: {
        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },
});
