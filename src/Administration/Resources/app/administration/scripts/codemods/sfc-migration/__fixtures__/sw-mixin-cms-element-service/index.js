import template from './sw-mixin-cms-element-service.html.twig';

/**
 * @sw-package framework
 */
export default {
    template,

    mixins: [
        Shopware.Mixin.getByName('cms-element'),
    ],

    computed: {
        // `cmsService` reached the component through the mixin's own injection; the composable resolves
        // its own copy and returns none, so this read has nothing to resolve against afterwards.
        speedDefault() {
            return this.cmsService.getCmsElementConfigByName('image-slider').defaultConfig.speed.value;
        },
    },
};
