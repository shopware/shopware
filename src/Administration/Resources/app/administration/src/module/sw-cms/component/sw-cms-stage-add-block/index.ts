import template from './sw-cms-stage-add-block.html.twig';
import './sw-cms-stage-add-block.scss';

/**
 * @private
 * @package buyers-experience
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    compatConfig: Shopware.compatConfig,

    emits: ['stage-block-add'],
});
