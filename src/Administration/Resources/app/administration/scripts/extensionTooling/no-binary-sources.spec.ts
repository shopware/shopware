/**
 * @sw-package framework
 *
 * git's binary heuristic looks for a NUL byte in the first 8000 bytes, so a
 * literal NUL in a source file silently turns its diffs into "Bin N -> M
 * bytes" and hides it from textual review. Key separators must use the
 * \u0000 escape instead (see check.ts).
 */

import fs from 'fs';
import path from 'path';

describe('scripts/extensionTooling sources', () => {
    it('contain no literal NUL byte, so git diffs stay textual', () => {
        const sourceFiles = (fs.readdirSync(__dirname, { recursive: true }) as string[])
            .filter((file) => file.endsWith('.ts'))
            .map((file) => path.join(__dirname, file));

        expect(sourceFiles.length).toBeGreaterThan(0);
        expect(sourceFiles.filter((file) => fs.readFileSync(file).includes(0))).toEqual([]);
    });
});
