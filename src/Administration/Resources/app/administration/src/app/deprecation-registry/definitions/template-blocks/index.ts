/**
 * @sw-package framework
 */

import removedIn68 from './removed-in-6-8';
import renamedIn68 from './renamed-in-6-8';
import type { TemplateBlockMigration } from '../helpers';

// Removals first: a name both lists use is reported once, by the first migration that lists it
export default [
    removedIn68,
    renamedIn68,
] satisfies TemplateBlockMigration[];
