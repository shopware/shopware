/**
 * @sw-package framework
 *
 * The entry of the bundle that `build.ts` builds for `index.html`.
 */
import { startPage } from './page';

/** Set by `build.ts`. */
declare const PUBLIC_API_MAJOR_FLAGS: readonly string[];

startPage({
    document,
    location: window.location,
    history: window.history,
    fetch: window.fetch.bind(window),
    caches: typeof caches === 'undefined' ? undefined : caches,
    repository: 'shopware/shopware',
    administration: 'src/Administration/Resources/app/administration',
    majorFlags: PUBLIC_API_MAJOR_FLAGS,
});
