import template from './sw-cms-stage-add-block.html.twig';
import './sw-cms-stage-add-block.scss';

/**
 * @private
 * @sw-package discovery
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    emits: ['stage-block-add'],
});
