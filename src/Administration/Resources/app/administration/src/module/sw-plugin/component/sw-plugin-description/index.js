import template from './sw-plugin-description.html.twig';

/**
 * @feature-deprecated (flag:FEATURE_NEXT_12608) tag:v6.4.0
 * Deprecation notice: The whole plugin manager will be removed with 6.4.0 and replaced
 * by the extension module.
 * When removing the feature flag for FEATURE_NEXT_12608, also merge the merge request
 * for NEXT-13821 which removes the plugin manager.
 */

Shopware.Component.register('sw-plugin-description', {
    template,

    props: {
        namespace: {
            type: String,
            required: true
        },

        description: {
            type: Object,
            required: true
        }
    }
});
