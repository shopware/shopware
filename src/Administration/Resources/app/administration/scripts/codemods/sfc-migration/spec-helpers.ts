/**
 * @sw-package framework
 */

/**
 * Temp-tree helpers shared by the codemod specs. Kept out of the specs themselves so the three
 * suites that build a throwaway component tree agree on what "the tree before and after" means.
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';

function makeRoot(prefix: string): string {
    return fs.mkdtempSync(path.join(os.tmpdir(), prefix));
}

function writeFile(root: string, relativePath: string, contents: string | Buffer): string {
    const file = path.join(root, relativePath);

    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, contents);

    return file;
}

/** Every file below `root`, by relative path, so a run can be proven byte-for-byte non-destructive. */
function manifest(root: string): Record<string, Buffer> {
    const files: string[] = [];
    const visit = (directory: string): void => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const file = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(file);
                return;
            }

            files.push(file);
        });
    };

    visit(root);

    return Object.fromEntries(
        files.sort().map((file) => [
            path.relative(root, file).split(path.sep).join('/'),
            fs.readFileSync(file),
        ]),
    );
}

export { makeRoot, manifest, writeFile };
