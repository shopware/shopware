/**
 * @package admin
 */

import baseComponents from 'src/app/component/components';
import registerAsyncComponents from 'src/app/asyncComponent/asyncComponents';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default async function initializeBaseComponents() {
    if (window._features_.ADMIN_VITE) {
        registerAsyncComponents();

        // eslint-disable-next-line no-restricted-syntax
        for (const component of baseComponents()) {
            // eslint-disable-next-line @typescript-eslint/no-unsafe-call,no-await-in-loop
            await component();
        }

        return Promise.resolve();
    }

    registerAsyncComponents();

    // eslint-disable-next-line @typescript-eslint/no-unsafe-return
    return baseComponents();
}
