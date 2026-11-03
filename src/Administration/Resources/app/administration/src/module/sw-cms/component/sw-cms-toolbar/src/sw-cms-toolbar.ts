import template from './sw-cms-toolbar.html.twig';
import './sw-cms-toolbar.scss';
import { wrapComponentConfig } from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default wrapComponentConfig({
    template,
});
