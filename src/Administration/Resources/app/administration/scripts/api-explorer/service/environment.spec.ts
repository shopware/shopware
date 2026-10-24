/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { createTestRepo, TEST_ADMIN_PATH } from '../git/test-helpers';
import { collectEnvironment, EXTRACTOR_SOURCES, hashExtractorSources } from './environment';

describe('extraction environment key', () => {
    let tmpDir: string;

    const write = (file: string, content: string): void => {
        fs.mkdirSync(path.dirname(path.join(tmpDir, file)), { recursive: true });
        fs.writeFileSync(path.join(tmpDir, file), content);
    };
    const hash = () => hashExtractorSources([path.join(tmpDir, 'snapshot')]);

    beforeEach(() => {
        tmpDir = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-environment-'));
        write('snapshot/extract.ts', 'export const value = 1;\n');
        write('snapshot/extract.spec.ts', 'it("works", () => {});\n');
        write('snapshot/__fixtures__/admin-root/src/index.ts', 'export {};\n');
    });

    afterEach(() => {
        fs.rmSync(tmpDir, { recursive: true, force: true });
    });

    it('changes when an extractor source file changes, but not for specs and fixtures', () => {
        const initial = hash();

        write('snapshot/extract.spec.ts', 'it("still works", () => {});\n');
        write('snapshot/__fixtures__/admin-root/src/index.ts', 'export const changed = true;\n');

        expect(hash()).toBe(initial);

        write('snapshot/extract.ts', 'export const value = 2;\n');

        expect(hash()).not.toBe(initial);
    });

    it('covers existing extractor sources', () => {
        EXTRACTOR_SOURCES.forEach((entry) => expect(fs.existsSync(entry)).toBe(true));
    });

    it('gives a different cache key for different extractor sources', async () => {
        const repo = createTestRepo();

        try {
            const before = await collectEnvironment(repo.repoRoot, TEST_ADMIN_PATH, hash());
            write('snapshot/extract.ts', 'export const value = 3;\n');
            const after = await collectEnvironment(repo.repoRoot, TEST_ADMIN_PATH, hash());

            expect(after.key).not.toBe(before.key);
            expect(after.key).toMatch(/^[0-9a-f]{12}$/);
        } finally {
            repo.cleanup();
        }
    });
});
