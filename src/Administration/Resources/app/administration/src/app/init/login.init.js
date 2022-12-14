/**
 * @package admin
 */

import { login } from 'src/module';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function initializeAppModules() {
    return login();
}
