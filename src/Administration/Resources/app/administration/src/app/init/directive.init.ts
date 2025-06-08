/* istanbul ignore file */

/**
 * @sw-package framework
 */

import directives from 'src/app/directive';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createAppDirectives(): void {
    directives();
}
