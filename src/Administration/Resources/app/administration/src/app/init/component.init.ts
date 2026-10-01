/**
 * @sw-package framework
 */

import registerComponents from 'src/app/component';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeBaseComponents() {
    registerComponents();

    return Promise.resolve();
}
