/**
 * @sw-package framework
 */

import template from './sw-settings-services-hero.html.twig';
import './sw-settings-services-hero.scss';
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
        const assetFilter = Shopware.Filter.getByName('asset');

        return {
            assets: {
                imageEditor: assetFilter('/administration/administration/static/img/services/image-editor.svg'),
                previewGenerator: assetFilter('/administration/administration/static/img/services/3d-preview-generator.svg'),
                copilot: assetFilter('/administration/administration/static/img/services/copilot.svg'),
            },
        };
    },
});
