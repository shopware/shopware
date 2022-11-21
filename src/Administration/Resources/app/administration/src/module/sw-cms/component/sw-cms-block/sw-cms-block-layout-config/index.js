import template from './sw-cms-block-layout-config.html.twig';
import './sw-cms-block-layout-config.scss';

/**
 * @private
 */
export default {
    template,

    inject: ['cmsService'],

    props: {
        block: {
            type: Object,
            required: true,
        },
    },
};
