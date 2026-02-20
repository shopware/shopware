import template from './sw-scene-sidebar-collapse.html.twig';

/**
 * @private
 * @sw-package innovation
 *
 * @experimental stableVersion:v6.8.0 feature:SpatialSceneEditor
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        summary: {
            type: String,
            required: true
        },
        isLast: {
            type: Boolean,
            default: false
        },
        expandOnLoading: {
            type: Boolean,
            required: false,
            default: true,
        },
    },
});
