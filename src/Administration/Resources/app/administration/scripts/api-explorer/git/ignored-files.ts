/**
 * @sw-package framework
 */

import * as path from 'node:path';
import { normalizeAdminPath } from './materialize';
import { runGit } from './run-git';

/**
 * Repository relative paths of git-ignored `.d.ts` files below `<admin>/src` in the checkout, sorted, e.g. the
 * generated entity schema. They exist in no commit, but the working tree program type-checks against them.
 */
export async function listIgnoredDeclarationFiles(repoRoot: string, adminRelativePath: string): Promise<string[]> {
    const output = await runGit(repoRoot, [
        'ls-files',
        '--others',
        '--ignored',
        '--exclude-standard',
        '--full-name',
        '-z',
        '--',
        path.posix.join(normalizeAdminPath(adminRelativePath), 'src'),
    ]);

    return output
        .split('\0')
        .filter((file) => file.endsWith('.d.ts'))
        .sort();
}
