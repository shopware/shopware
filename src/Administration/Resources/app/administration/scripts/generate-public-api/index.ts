/**
 * @sw-package framework
 *
 * Regenerates the committed public API snapshot in `public-api/`:
 *
 *     composer admin:generate-public-api
 *
 * `committed-snapshot.spec.ts` fails when the committed files differ from what this writes.
 */
import { generateCurrentSnapshot, writeCommittedSnapshot } from './committed-snapshot';

writeCommittedSnapshot(generateCurrentSnapshot());
