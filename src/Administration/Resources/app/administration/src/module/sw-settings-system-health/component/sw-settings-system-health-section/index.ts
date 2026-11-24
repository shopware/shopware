import template from './sw-settings-system-health-section.html.twig';
import './sw-settings-system-health-section.scss';

/**
 * One labeled part of a check's details panel: "What happened", "What to do", "Details", "About this check".
 *
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        title: {
            type: String,
            required: true,
        },
    },
});
