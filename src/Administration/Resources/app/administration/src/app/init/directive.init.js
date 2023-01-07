/**
 * @package admin
 */

import directives from 'src/app/directive';

const createdAppDirectives = directives();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createAppDirectives() {
    return createdAppDirectives;
}
