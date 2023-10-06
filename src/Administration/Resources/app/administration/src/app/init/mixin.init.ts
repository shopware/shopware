/**
 * @package admin
 */

import mixin from 'src/app/mixin';

const createdAppMixin = mixin();

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function createAppMixin() {
    return createdAppMixin;
}
