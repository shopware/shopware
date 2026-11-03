/**
 * @sw-package framework
 * @private
 *
 * Writes the mini-module block of the repository's `.codeowners` from `mini-module-owners.json`.
 *
 *     composer admin:generate-mini-module-owners
 *
 * `owners.spec.ts` fails when the block drifts from the manifest.
 */

import fs from 'node:fs';
import { CODEOWNERS_FILE, expectedCodeowners } from './owners';

if (require.main === module) {
    fs.writeFileSync(CODEOWNERS_FILE, expectedCodeowners());

    // eslint-disable-next-line no-console -- the script reports what it wrote
    console.log('Wrote the mini-module block of .codeowners.');
}
