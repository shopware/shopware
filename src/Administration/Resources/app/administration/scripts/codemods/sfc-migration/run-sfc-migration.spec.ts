import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path, { join } from 'node:path';
import { findTwigFile, normaliseJsContent, runMigration } from './run-sfc-migration';

const FIXTURES_DIR = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return readFileSync(join(FIXTURES_DIR, name), 'utf-8');
}

function createTempDir(): string {
    return mkdtempSync(join(tmpdir(), 'sfc-migration-test-'));
}

function makeComponent(baseDir: string, componentName: string, jsContent: string, twigContent?: string): string {
    const dir = join(baseDir, componentName);
    mkdirSync(dir, { recursive: true });
    writeFileSync(join(dir, 'index.js'), jsContent, 'utf-8');
    if (twigContent !== undefined) {
        writeFileSync(join(dir, `${componentName}.html.twig`), twigContent, 'utf-8');
    }
    return dir;
}

describe('findTwigFile', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('returns the twig file path when a .html.twig file exists', () => {
        writeFileSync(join(tmpDir, 'my-component.html.twig'), '<div/>', 'utf-8');
        const result = findTwigFile(tmpDir);
        expect(result).toBe(join(tmpDir, 'my-component.html.twig'));
    });

    it('returns null when no .html.twig file is present', () => {
        writeFileSync(join(tmpDir, 'index.js'), 'export default {}', 'utf-8');
        const result = findTwigFile(tmpDir);
        expect(result).toBeNull();
    });
});

describe('normaliseJsContent', () => {
    it('is a no-op when the content already uses Shopware.Component.register', () => {
        const input = readFixture('simple-component.index.js');
        const result = normaliseJsContent(input, 'sw-simple-card');
        expect(result).toBe(input);
    });

    it('wraps export default {} with Shopware.Component.register()', () => {
        const input = `export default {\n    name: 'foo',\n};`;
        const result = normaliseJsContent(input, 'sw-foo');
        expect(result).toContain(`Shopware.Component.register('sw-foo', {`);
        expect(result).toContain('});');
        expect(result).not.toContain('export default');
    });

    it('handles a multiline export default with nested objects', () => {
        const input = `export default {\n    data() {\n        return {\n            x: 1,\n        };\n    },\n};`;
        const result = normaliseJsContent(input, 'sw-multi');
        expect(result).toContain(`Shopware.Component.register('sw-multi', {`);
        // inner `};` must not be replaced — only the outermost one
        expect(result).toContain('return {\n            x: 1,\n        };');
        expect(result.endsWith('});')).toBe(true);
    });

    it('does not match a module-level `};` that appears before the export default', () => {
        const input = [
            `const DEFAULT_CONFIG = {`,
            `    timeout: 5000,`,
            `};`,
            ``,
            `export default {`,
            `    data() { return {}; },`,
            `};`,
        ].join('\n');
        const result = normaliseJsContent(input, 'sw-config');
        expect(result).toContain(`Shopware.Component.register('sw-config', {`);
        expect(result).toContain('const DEFAULT_CONFIG = {');
        expect(result).not.toContain('export default');
    });

    it('does not corrupt trailing module-level code that contains `};` after the export default', () => {
        // This is the actual bug: the old lastIndexOf('};') would have matched the
        // TRAILING constant's closing `};` instead of the export default's.
        const input = [
            `export default {`,
            `    data() { return { x: 1 }; },`,
            `};`,
            ``,
            `const TRAILING = {`,
            `    timeout: 5000,`,
            `};`,
        ].join('\n');
        const result = normaliseJsContent(input, 'sw-trailing');
        expect(result).toContain(`Shopware.Component.register('sw-trailing', {`);
        // trailing const must survive unchanged
        expect(result).toContain('const TRAILING = {');
        expect(result).toContain('    timeout: 5000,');
        // export default keyword must be gone
        expect(result).not.toContain('export default');
    });
});

describe('runMigration — dry-run (default)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeAll(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-simple-card',
            readFixture('simple-component.index.js'),
            readFixture('simple-component.html.twig'),
        );
    });

    afterAll(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('does not write any .vue file to disk', () => {
        runMigration(tmpDir, { dryRun: true });
        expect(existsSync(join(componentDir, 'sw-simple-card.vue'))).toBe(false);
    });

    it('returns fully-migrated status in stats', () => {
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.fullyMigrated).toBe(1);
        expect(stats.partiallyMigrated).toBe(0);
        expect(stats.notMigratable).toBe(0);
        expect(stats.skipped).toBe(0);
    });

    it('report line contains [DRY RUN] prefix', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        expect(report).toHaveLength(1);
        expect(report[0]).toContain('[DRY RUN] Would write:');
        expect(report[0]).toContain('sw-simple-card.vue');
    });

    it('report line contains fully-migrated label', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        expect(report[0]).toContain('fully-migrated');
    });
});

describe('runMigration — write mode', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-simple-card',
            readFixture('simple-component.index.js'),
            readFixture('simple-component.html.twig'),
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('writes the .vue file to disk', () => {
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-simple-card.vue'))).toBe(true);
    });

    it('written file contains <template> and <script setup> sections', () => {
        runMigration(tmpDir, { dryRun: false });
        const content = readFileSync(join(componentDir, 'sw-simple-card.vue'), 'utf-8');
        expect(content).toContain('<template>');
        expect(content).toContain('<script setup>');
    });

    it('returns correct stats', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false });
        expect(stats.fullyMigrated).toBe(1);
        expect(stats.partiallyMigrated).toBe(0);
    });

    it('report line does not contain [DRY RUN] prefix', () => {
        const { report } = runMigration(tmpDir, { dryRun: false });
        expect(report[0]).not.toContain('[DRY RUN]');
        expect(report[0]).toContain('fully-migrated');
        expect(report[0]).toContain('sw-simple-card.vue');
    });
});

describe('runMigration — skip (no twig file)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeAll(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-no-twig',
            readFixture('simple-component.index.js'),
            // no twig content → no .html.twig created
        );
    });

    afterAll(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments skipped count', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false });
        expect(stats.skipped).toBe(1);
        expect(stats.fullyMigrated).toBe(0);
    });

    it('does not write any .vue file', () => {
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-no-twig.vue'))).toBe(false);
    });

    it('report line contains SKIP (no twig)', () => {
        const { report } = runMigration(tmpDir, { dryRun: false });
        expect(report[0]).toContain('SKIP (no twig)');
    });
});

describe('runMigration — not-migratable (render function)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeAll(() => {
        tmpDir = createTempDir();
        // render-component has no .html.twig fixture, so provide a minimal one
        componentDir = makeComponent(
            tmpDir,
            'sw-render-component',
            readFixture('render-component.index.js'),
            '<div class="sw-render-component"></div>',
        );
    });

    afterAll(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments notMigratable count', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false });
        expect(stats.notMigratable).toBe(1);
        expect(stats.fullyMigrated).toBe(0);
    });

    it('does not write a .vue file even in write mode', () => {
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-render-component.vue'))).toBe(false);
    });

    it('report line contains ✗ and not-migratable', () => {
        const { report } = runMigration(tmpDir, { dryRun: false });
        expect(report[0]).toContain('✗');
        expect(report[0]).toContain('not-migratable');
    });
});

describe('runMigration — overwrite protection', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-simple-card',
            readFixture('simple-component.index.js'),
            readFixture('simple-component.html.twig'),
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('skips an existing .vue file and increments skippedExisting', () => {
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), 'existing content', 'utf-8');
        const { stats } = runMigration(tmpDir, { dryRun: false });
        expect(stats.skippedExisting).toBe(1);
        expect(stats.fullyMigrated).toBe(0);
    });

    it('preserves the existing .vue content when skipping', () => {
        const originalContent = 'existing content';
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), originalContent, 'utf-8');
        runMigration(tmpDir, { dryRun: false });
        const content = readFileSync(join(componentDir, 'sw-simple-card.vue'), 'utf-8');
        expect(content).toBe(originalContent);
    });

    it('report line contains SKIP (already exists) label', () => {
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), 'existing content', 'utf-8');
        const { report } = runMigration(tmpDir, { dryRun: false });
        expect(report[0]).toContain('SKIP (already exists)');
        expect(report[0]).toContain('sw-simple-card.vue');
    });

    it('overwrites the existing .vue file when force is true', () => {
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), 'existing content', 'utf-8');
        runMigration(tmpDir, { dryRun: false, force: true });
        const content = readFileSync(join(componentDir, 'sw-simple-card.vue'), 'utf-8');
        expect(content).not.toBe('existing content');
        expect(content).toContain('<template>');
    });

    it('counts as fully-migrated (not skippedExisting) when force is true', () => {
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), 'existing content', 'utf-8');
        const { stats } = runMigration(tmpDir, { dryRun: false, force: true });
        expect(stats.fullyMigrated).toBe(1);
        expect(stats.skippedExisting).toBe(0);
    });

    it('does not skip in dry-run mode (dry-run never writes, so existence is irrelevant)', () => {
        writeFileSync(join(componentDir, 'sw-simple-card.vue'), 'existing content', 'utf-8');
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.fullyMigrated).toBe(1);
        expect(stats.skippedExisting).toBe(0);
    });
});

describe('runMigration — partially-migrated (mixins)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeAll(() => {
        tmpDir = createTempDir();
        // mixin-component has no .html.twig fixture, so provide a minimal one
        componentDir = makeComponent(
            tmpDir,
            'sw-mixin-list',
            readFixture('mixin-component.index.js'),
            '<div class="sw-mixin-list"></div>',
        );
    });

    afterAll(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments partiallyMigrated count in dry-run', () => {
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.partiallyMigrated).toBe(1);
    });

    it('does not write .vue file in dry-run', () => {
        runMigration(tmpDir, { dryRun: true });
        expect(existsSync(join(componentDir, 'sw-mixin-list.vue'))).toBe(false);
    });

    it('writes .vue file in write mode', () => {
        rmSync(join(componentDir, 'sw-mixin-list.vue'), { force: true });
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-mixin-list.vue'))).toBe(true);
    });

    it('dry-run report line contains [DRY RUN] and blocker info', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        expect(report[0]).toContain('[DRY RUN] Would write:');
        expect(report[0]).toContain('partially-migrated');
        expect(report[0]).toContain('mixins');
    });
});
