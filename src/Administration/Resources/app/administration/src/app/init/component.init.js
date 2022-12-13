/**
 * @package admin
 */

import baseComponents from 'src/app/component/components';
import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeBaseComponents() {
    registerAsyncComponents();

    return baseComponents();
}
