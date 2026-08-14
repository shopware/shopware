/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as os from 'os';
import * as path from 'path';
import {
    getMigrationTemporaryPath,
    nodeFileOps,
    writeMigration,
    type FileOps,
    type MigrationWriteInput,
} from './migration-writer';

const ORIGINAL_INDEX = Buffer.from("export default { name: 'sw-example' };\n");
const REPLACEMENT_INDEX = Buffer.from("export { default } from './sw-example.vue';\n");
const GENERATED_VUE = Buffer.from('<template><p>generated</p></template>\n');
const TWIG = Buffer.from('{% block sw_example %}<p>legacy</p>{% endblock %}\n');

type Fixture = {
    root: string;
    indexPath: string;
    vuePath: string;
    twigPath: string;
};

type FaultOptions = {
    shortWrites?: boolean;
    writeFailure?: 'vue' | 'index';
    renameFailure?: 'vue' | 'index';
    permissionFailure?: 'vue' | 'index';
    cleanupFailure?: 'vue' | 'index';
    events?: string[];
};

function createFixture(): Fixture {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'sfc-writer-'));
    const component = path.join(root, 'component');
    const indexPath = path.join(component, 'index.js');
    const vuePath = path.join(component, 'sw-example.vue');
    const twigPath = path.join(component, 'sw-example.html.twig');

    fs.mkdirSync(component);
    fs.writeFileSync(indexPath, ORIGINAL_INDEX);
    fs.writeFileSync(twigPath, TWIG);

    return { root, indexPath, vuePath, twigPath };
}

function createInput(fixture: Fixture, mode: MigrationWriteInput['mode'] = 'draft'): MigrationWriteInput {
    return {
        vuePath: fixture.vuePath,
        indexPath: fixture.indexPath,
        twigPath: fixture.twigPath,
        vueBytes: GENERATED_VUE,
        originalIndexBytes: ORIGINAL_INDEX,
        replacementIndexBytes: REPLACEMENT_INDEX,
        mode,
    };
}

function manifest(root: string): Record<string, Buffer> {
    const files: string[] = [];

    const visit = (directory: string): void => {
        fs.readdirSync(directory, { withFileTypes: true }).forEach((entry) => {
            const filePath = path.join(directory, entry.name);

            if (entry.isDirectory()) {
                visit(filePath);
                return;
            }

            files.push(filePath);
        });
    };

    visit(root);

    return Object.fromEntries(
        files.sort().map((filePath) => [
            path.relative(root, filePath).split(path.sep).join('/'),
            fs.readFileSync(filePath),
        ]),
    );
}

function expectedManifest(
    fixture: Fixture,
    options: { vue?: Buffer; index?: Buffer; temporary?: Buffer } = {},
): Record<string, Buffer> {
    const expected: Record<string, Buffer> = {
        'component/index.js': options.index ?? ORIGINAL_INDEX,
        'component/sw-example.html.twig': TWIG,
    };

    if (options.vue) {
        expected['component/sw-example.vue'] = options.vue;
    }

    if (options.temporary) {
        expected['component/.sw-example.vue.sfc-migration.tmp'] = options.temporary;
    }

    // Keep the fixture argument in the helper signature so every expected manifest is tied to the
    // exact temp tree under test, even though the relative manifest keys are stable.
    expect(fixture.root).toBeTruthy();

    return expected;
}

function fileKind(filePath: string): 'vue' | 'index' | 'other' {
    if (filePath.includes('sw-example.vue')) {
        return 'vue';
    }

    if (filePath.includes('index.js')) {
        return 'index';
    }

    return 'other';
}

function createFaultyFileOps(options: FaultOptions): FileOps {
    const handles = new Map<number, 'vue' | 'index' | 'other'>();
    const failed = new Set<string>();
    const events = options.events ?? [];

    const failOnce = (key: string, message: string): void => {
        if (failed.has(key)) {
            return;
        }

        failed.add(key);
        throw new Error(message);
    };

    return {
        readFile: (filePath) => nodeFileOps.readFile(filePath),
        exists: (filePath) => nodeFileOps.exists(filePath),
        openExclusive: (filePath) => {
            const kind = fileKind(filePath);
            events.push(`open:${kind}`);

            if (options.permissionFailure === kind) {
                failOnce(`permission:${kind}`, `EACCES opening ${kind} temporary file`);
            }

            const handle = nodeFileOps.openExclusive(filePath);

            handles.set(handle, kind);
            return handle;
        },
        write: (handle, bytes, offset, length) => {
            const kind = handles.get(handle) ?? 'other';
            events.push(`write:${kind}`);

            if (options.writeFailure === kind) {
                const prefixLength = Math.min(3, length);
                const written = nodeFileOps.write(handle, bytes, offset, prefixLength);

                failOnce(`write:${kind}`, `short write failure after ${written} bytes`);
            }

            const writeLength = options.shortWrites ? Math.min(1, length) : length;

            return nodeFileOps.write(handle, bytes, offset, writeLength);
        },
        sync: (handle) => {
            events.push(`sync:${handles.get(handle) ?? 'other'}`);
            nodeFileOps.sync(handle);
        },
        close: (handle) => {
            events.push(`close:${handles.get(handle) ?? 'other'}`);
            nodeFileOps.close(handle);
            handles.delete(handle);
        },
        rename: (source, target) => {
            const kind = fileKind(target);
            events.push(`rename:${kind}`);

            if (options.renameFailure === kind) {
                failOnce(`rename:${kind}`, `rename failure for ${kind}`);
            }

            nodeFileOps.rename(source, target);
        },
        remove: (filePath) => {
            const kind = fileKind(filePath);
            events.push(`remove:${kind}`);

            if (options.cleanupFailure === kind) {
                failOnce(`remove:${kind}`, `cleanup failure for ${kind}`);
            }

            nodeFileOps.remove(filePath);
        },
    };
}

describe('scripts/codemods/sfc-migration/migration-writer', () => {
    let fixture: Fixture;

    beforeEach(() => {
        fixture = createFixture();
    });

    afterEach(() => {
        fs.rmSync(fixture.root, { recursive: true, force: true });
    });

    it('writes a draft through a same-directory temp file and retains the index and Twig bytes', () => {
        const before = manifest(fixture.root);
        const result = writeMigration(createInput(fixture));

        expect(result).toMatchObject({ state: 'draft-written', recovery: 'none', changed: true });
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE }));
        expect(fs.existsSync(getMigrationTemporaryPath(fixture.vuePath))).toBe(false);
        expect(before['component/index.js']).toEqual(ORIGINAL_INDEX);
        expect(before['component/sw-example.html.twig']).toEqual(TWIG);
    });

    it('loops short writes and closes each staged file before renaming it', () => {
        const events: string[] = [];
        const result = writeMigration(
            createInput(fixture, 'replace-originals'),
            createFaultyFileOps({ shortWrites: true, events }),
        );

        expect(result).toMatchObject({ state: 'replaced', recovery: 'replaced', changed: true });
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE, index: REPLACEMENT_INDEX }));
        expect(events.indexOf('close:vue')).toBeLessThan(events.indexOf('rename:vue'));
        expect(events.indexOf('close:index')).toBeLessThan(events.indexOf('rename:index'));
    });

    it('reports a matching draft as replacement-ready and resumes replacement explicitly', () => {
        const draft = writeMigration(createInput(fixture));
        const beforeResume = manifest(fixture.root);
        const ready = writeMigration(createInput(fixture));

        expect(draft.state).toBe('draft-written');
        expect(ready).toMatchObject({ state: 'replacement-ready', recovery: 'replacement-ready', changed: false });
        expect(manifest(fixture.root)).toEqual(beforeResume);

        const replaced = writeMigration(createInput(fixture, 'replace-originals'));

        expect(replaced).toMatchObject({ state: 'replaced', recovery: 'replaced', changed: true });
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE, index: REPLACEMENT_INDEX }));
    });

    it('treats an already replaced component as an idempotent rerun', () => {
        writeMigration(createInput(fixture, 'replace-originals'));
        const before = manifest(fixture.root);
        const result = writeMigration(createInput(fixture, 'replace-originals'));

        expect(result).toMatchObject({ state: 'replaced', recovery: 'replaced', changed: false });
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('recovers a replacement index whose generated Vue file is missing', () => {
        fs.writeFileSync(fixture.indexPath, REPLACEMENT_INDEX);

        const result = writeMigration(createInput(fixture, 'replace-originals'));

        expect(result).toMatchObject({ state: 'replaced', recovery: 'replaced', changed: true });
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE, index: REPLACEMENT_INDEX }));
    });

    it.each([
        [
            'a foreign Vue file',
            Buffer.from('<template><p>foreign</p></template>\n'),
        ],
        [
            'a divergent generated Vue file',
            Buffer.from('<template><p>older draft</p></template>\n'),
        ],
    ])('protects %s without changing the manifest', (_label, existingVue) => {
        fs.writeFileSync(fixture.vuePath, existingVue);
        const before = manifest(fixture.root);

        const result = writeMigration(createInput(fixture));

        expect(result).toMatchObject({ state: 'conflict', recovery: 'manual-conflict', changed: false });
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('protects an index that differs from both the original and replacement bytes', () => {
        fs.writeFileSync(fixture.indexPath, Buffer.from('export default { changed: true };\n'));
        const before = manifest(fixture.root);

        const result = writeMigration(createInput(fixture, 'replace-originals'));

        expect(result).toMatchObject({ state: 'conflict', recovery: 'manual-conflict', changed: false });
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('keeps all visible files unchanged after a Vue prefix-write failure', () => {
        const before = manifest(fixture.root);
        const result = writeMigration(createInput(fixture), createFaultyFileOps({ writeFailure: 'vue' }));

        expect(result).toMatchObject({ state: 'error', recovery: 'retry-safe', changed: false });
        expect(result.error?.stage).toBe('vue-write');
        expect(result.temporaryPaths).toEqual([]);
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('keeps all visible files unchanged after a Vue rename failure', () => {
        const before = manifest(fixture.root);
        const result = writeMigration(createInput(fixture), createFaultyFileOps({ renameFailure: 'vue' }));

        expect(result).toMatchObject({ state: 'error', recovery: 'retry-safe', changed: false });
        expect(result.error?.stage).toBe('vue-rename');
        expect(result.temporaryPaths).toEqual([]);
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('retains the original index and reports replacement-ready after an index prefix-write failure', () => {
        const result = writeMigration(
            createInput(fixture, 'replace-originals'),
            createFaultyFileOps({ writeFailure: 'index' }),
        );

        expect(result).toMatchObject({ state: 'error', recovery: 'replacement-ready', changed: true });
        expect(result.error?.stage).toBe('index-write');
        expect(result.temporaryPaths).toEqual([]);
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE }));
    });

    it('retains the original index and reports replacement-ready after an index rename failure', () => {
        const result = writeMigration(
            createInput(fixture, 'replace-originals'),
            createFaultyFileOps({ renameFailure: 'index' }),
        );

        expect(result).toMatchObject({ state: 'error', recovery: 'replacement-ready', changed: true });
        expect(result.error?.stage).toBe('index-rename');
        expect(result.temporaryPaths).toEqual([]);
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE }));
    });

    it('reports a pending temp file when cleanup itself fails', () => {
        const result = writeMigration(
            createInput(fixture),
            createFaultyFileOps({ writeFailure: 'vue', cleanupFailure: 'vue' }),
        );

        expect(result).toMatchObject({ state: 'error', recovery: 'cleanup-pending', changed: false });
        expect(result.error?.stage).toBe('vue-write');
        expect(result.cleanupFailures).toEqual([expect.stringContaining('remove')]);
        expect(result.temporaryPaths).toEqual([getMigrationTemporaryPath(fixture.vuePath)]);
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { temporary: GENERATED_VUE.subarray(0, 3) }));
    });

    it('leaves the tree unchanged when Vue temp creation is denied', () => {
        const before = manifest(fixture.root);
        const result = writeMigration(createInput(fixture), createFaultyFileOps({ permissionFailure: 'vue' }));

        expect(result).toMatchObject({ state: 'error', recovery: 'retry-safe', changed: false });
        expect(result.error?.stage).toBe('vue-write');
        expect(manifest(fixture.root)).toEqual(before);
    });

    it('leaves the original index intact when index temp creation is denied', () => {
        const result = writeMigration(
            createInput(fixture, 'replace-originals'),
            createFaultyFileOps({ permissionFailure: 'index' }),
        );

        expect(result).toMatchObject({ state: 'error', recovery: 'replacement-ready', changed: true });
        expect(result.error?.stage).toBe('index-write');
        expect(manifest(fixture.root)).toEqual(expectedManifest(fixture, { vue: GENERATED_VUE }));
    });
});
