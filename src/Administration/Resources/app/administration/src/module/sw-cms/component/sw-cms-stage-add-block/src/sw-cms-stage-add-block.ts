import template from './sw-cms-stage-add-block.html.twig';
import './sw-cms-stage-add-block.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @private
 * @sw-package discovery
 */
export default wrapComponentConfig({
    template,

    emits: ['stage-block-add'],
});
