import fs, { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path, { join } from 'node:path';
import { findTwigFile, getCliUsage, normaliseJsContent, parseCliOptions, runMigration } from './run-sfc-migration';

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

describe('parseCliOptions', () => {
    it('sets help when --help is provided', () => {
        expect(parseCliOptions(['--help'])).toMatchObject({
            help: true,
            targetDir: undefined,
            dryRun: true,
        });
    });

    it('sets help when -h is provided', () => {
        expect(parseCliOptions(['-h'])).toMatchObject({
            help: true,
            targetDir: undefined,
            dryRun: true,
        });
    });

    it('keeps dry-run mode by default', () => {
        const result = parseCliOptions(['src/app/component/base/sw-button']);

        expect(result).toMatchObject({
            targetDir: path.resolve('src/app/component/base/sw-button'),
            dryRun: true,
            force: false,
            deleteOriginals: false,
        });
    });

    it('disables dry-run mode when --write is provided', () => {
        const result = parseCliOptions([
            '--write',
            'src/app/component/base/sw-button',
        ]);

        expect(result.dryRun).toBe(false);
    });

    it('lets --dry-run win when both --dry-run and --write are provided', () => {
        const result = parseCliOptions([
            '--dry-run',
            '--write',
            'src/app/component/base/sw-button',
        ]);

        expect(result.dryRun).toBe(true);
    });

    it('parses --force and --delete-originals flags', () => {
        const result = parseCliOptions([
            '--write',
            '--force',
            '--delete-originals',
            'src/app/component/base/sw-button',
        ]);

        expect(result.force).toBe(true);
        expect(result.deleteOriginals).toBe(true);
    });

    it('leaves targetDir empty when no path is provided', () => {
        const result = parseCliOptions(['--write']);

        expect(result.targetDir).toBeUndefined();
    });

    it('builds usage output from the option definitions', () => {
        const usage = getCliUsage();

        expect(usage).toContain('SFC Migration Codemod');
        expect(usage).toContain('--dry-run');
        expect(usage).toContain('--delete-originals');
    });
});

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
        const result = findTwigFile(tmpDir, 'my-component');
        expect(result).toBe(join(tmpDir, 'my-component.html.twig'));
    });

    it('prefers the twig file that matches the component name', () => {
        writeFileSync(join(tmpDir, 'helper.html.twig'), '<div>helper</div>', 'utf-8');
        writeFileSync(join(tmpDir, 'sw-foo.html.twig'), '<div>component</div>', 'utf-8');

        const result = findTwigFile(tmpDir, 'sw-foo');

        expect(result).toBe(join(tmpDir, 'sw-foo.html.twig'));
    });

    it('returns null when two .html.twig files are present that do not match the component name', () => {
        writeFileSync(join(tmpDir, 'helper.html.twig'), '<div>helper</div>', 'utf-8');
        writeFileSync(join(tmpDir, 'sidebar.html.twig'), '<div>sidebar</div>', 'utf-8');

        const result = findTwigFile(tmpDir, 'sw-foo');

        expect(result).toBeNull();
    });

    it('returns null when no .html.twig file is present', () => {
        writeFileSync(join(tmpDir, 'index.js'), 'export default {}', 'utf-8');
        const result = findTwigFile(tmpDir, 'missing-component');
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

    it('escapes component names when wrapping export default {}', () => {
        const input = `export default {};`;
        const result = normaliseJsContent(input, `sw-foo's-card`);

        expect(result).toBe(`Shopware.Component.register('sw-foo\\'s-card', {});`);
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

describe('runMigration — target validation', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('throws when the target path does not exist', () => {
        const missingPath = join(tmpDir, 'missing');

        expect(() => runMigration(missingPath, { dryRun: true })).toThrow(`Target path does not exist: ${missingPath}`);
    });

    it('throws when the target path is a file', () => {
        const filePath = join(tmpDir, 'component.js');
        writeFileSync(filePath, 'export default {};', 'utf-8');

        expect(() => runMigration(filePath, { dryRun: true })).toThrow(`Target path must be a directory: ${filePath}`);
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

describe('runMigration — report paths', () => {
    let originalCwd: string;
    let tmpDir: string;

    beforeEach(() => {
        originalCwd = process.cwd();
        tmpDir = createTempDir();
        process.chdir(tmpDir);
        tmpDir = process.cwd();
    });

    afterEach(() => {
        process.chdir(originalCwd);
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('prints component report paths relative to the current working directory', () => {
        makeComponent(
            join(tmpDir, 'src/module/sw-dashboard/page'),
            'sw-dashboard-index',
            'const config = {};',
            '<div class="sw-dashboard-index"></div>',
        );
        makeComponent(join(tmpDir, 'src/module'), 'sw-customer', readFixture('simple-component.index.js'));
        makeComponent(
            join(tmpDir, 'src/module/sw-customer/view'),
            'sw-customer-detail-order',
            readFixture('simple-component.index.js'),
            readFixture('simple-component.html.twig'),
        );
        makeComponent(
            join(tmpDir, 'src/module/sw-customer/view'),
            'sw-customer-detail-addresses',
            readFixture('mixin-component.index.js'),
            '<div class="sw-customer-detail-addresses"></div>',
        );

        const { report } = runMigration(join(tmpDir, 'src/module'), { dryRun: true });

        expect(report).toContain(
            '✗  not-migratable      [no options object found]  ./src/module/sw-dashboard/page/sw-dashboard-index/index.js',
        );
        expect(report).toContain('SKIP (no twig)  ./src/module/sw-customer/index.js');
        expect(report).toContain(
            '✓  fully-migrated        [DRY RUN] Would write: ./src/module/sw-customer/view/sw-customer-detail-order/sw-customer-detail-order.vue',
        );
        expect(report).toContain(
            '~  partially-migrated  [mixins]  [DRY RUN] Would write: ./src/module/sw-customer/view/sw-customer-detail-addresses/sw-customer-detail-addresses.vue',
        );
        expect(report.join('\n')).not.toContain(tmpDir);
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

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-no-twig',
            readFixture('simple-component.index.js'),
            // no twig content → no .html.twig created
        );
    });

    afterEach(() => {
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

describe('runMigration — filesystem errors', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(tmpDir, 'sw-unreadable-twig-dir', readFixture('simple-component.index.js'));
    });

    afterEach(() => {
        jest.restoreAllMocks();
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('reports readdir errors as errors instead of no-twig skips', () => {
        const originalReaddirSync = fs.readdirSync.bind(fs) as (...args: unknown[]) => unknown;

        jest.spyOn(fs, 'readdirSync').mockImplementation(((...args: unknown[]) => {
            const [dir] = args;
            const isComponentDir = typeof dir === 'string' && dir === componentDir;
            const isSelectTwigFileCall = new Error().stack?.includes('selectTwigFile') ?? false;

            if (isComponentDir && isSelectTwigFileCall) {
                throw new Error('EACCES: permission denied, scandir');
            }

            return originalReaddirSync(...args);
        }) as typeof fs.readdirSync);

        const { stats, report } = runMigration(tmpDir, { dryRun: true });

        expect(stats.errors).toBe(1);
        expect(stats.skipped).toBe(0);
        expect(report[0]).toContain('ERROR');
        expect(report[0]).toContain('index.js');
        expect(report[0]).toContain('EACCES: permission denied, scandir');
    });
});

describe('runMigration — skip (ambiguous twig files)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(tmpDir, 'sw-ambiguous-card', readFixture('simple-component.index.js'));
        writeFileSync(join(componentDir, 'helper.html.twig'), '<div>helper</div>', 'utf-8');
        writeFileSync(join(componentDir, 'sidebar.html.twig'), '<div>sidebar</div>', 'utf-8');
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments skipped count', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false });
        expect(stats.skipped).toBe(1);
        expect(stats.fullyMigrated).toBe(0);
    });

    it('does not write any .vue file', () => {
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-ambiguous-card.vue'))).toBe(false);
    });

    it('does not delete originals even when deleteOriginals is true', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'index.js'))).toBe(true);
        expect(existsSync(join(componentDir, 'helper.html.twig'))).toBe(true);
        expect(existsSync(join(componentDir, 'sidebar.html.twig'))).toBe(true);
    });

    it('report line contains SKIP (ambiguous twig) and the candidate files', () => {
        const { report } = runMigration(tmpDir, { dryRun: false });
        expect(report[0]).toContain('SKIP (ambiguous twig)');
        expect(report[0]).toContain('helper.html.twig');
        expect(report[0]).toContain('sidebar.html.twig');
    });
});

describe('runMigration — not-migratable (render function)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        // render-component has no .html.twig fixture, so provide a minimal one
        componentDir = makeComponent(
            tmpDir,
            'sw-render-component',
            readFixture('render-component.index.js'),
            '<div class="sw-render-component"></div>',
        );
    });

    afterEach(() => {
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

describe('runMigration — not-migratable (unsupported twig template)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-extends-component',
            readFixture('simple-component.index.js'),
            "{% extends 'bar' %}{% block foo %}<div>content</div>{% endblock %}",
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments notMigratable count without incrementing errors', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false });

        expect(stats.notMigratable).toBe(1);
        expect(stats.errors).toBe(0);
    });

    it('does not write a .vue file even in write mode', () => {
        runMigration(tmpDir, { dryRun: false });

        expect(existsSync(join(componentDir, 'sw-extends-component.vue'))).toBe(false);
    });

    it('report line contains not-migratable with the twig blocker', () => {
        const { report } = runMigration(tmpDir, { dryRun: false });

        expect(report[0]).toContain('not-migratable');
        expect(report[0]).toContain('twig extends');
        expect(report[0]).not.toContain('ERROR');
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

    beforeEach(() => {
        tmpDir = createTempDir();
        // mixin-component has no .html.twig fixture, so provide a minimal one
        componentDir = makeComponent(
            tmpDir,
            'sw-mixin-list',
            readFixture('mixin-component.index.js'),
            '<div class="sw-mixin-list"></div>',
        );
    });

    afterEach(() => {
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
        runMigration(tmpDir, { dryRun: false });
        expect(existsSync(join(componentDir, 'sw-mixin-list.vue'))).toBe(true);
    });

    it('dry-run report line contains [DRY RUN] and blocker info', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        expect(report[0]).toContain('[DRY RUN] Would write:');
        expect(report[0]).toContain('partially-migrated');
        expect(report[0]).toContain('mixins');
    });

    it('skips an existing .vue file without counting it as partially migrated', () => {
        const originalContent = 'existing content';
        writeFileSync(join(componentDir, 'sw-mixin-list.vue'), originalContent, 'utf-8');

        const { report, stats } = runMigration(tmpDir, { dryRun: false });
        const content = readFileSync(join(componentDir, 'sw-mixin-list.vue'), 'utf-8');

        expect(content).toBe(originalContent);
        expect(stats.skippedExisting).toBe(1);
        expect(stats.partiallyMigrated).toBe(0);
        expect(report[0]).toContain('SKIP (already exists)');
    });
});

describe('runMigration — partially-migrated (extends)', () => {
    let tmpDir: string;

    beforeAll(() => {
        tmpDir = createTempDir();
        // extend-component has no .html.twig fixture, so provide a minimal one
        makeComponent(
            tmpDir,
            'sw-extended-button',
            readFixture('extend-component.index.js'),
            '<div class="sw-extended-button"></div>',
        );
    });

    afterAll(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments partiallyMigrated count', () => {
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.partiallyMigrated).toBe(1);
        expect(stats.fullyMigrated).toBe(0);
    });

    it('increments extendsComponents stat', () => {
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.extendsComponents).toBe(1);
    });

    it('report line contains partially-migrated and the parent component name', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        const mainLine = report.find((l) => l.includes('partially-migrated'));
        expect(mainLine).toBeDefined();
        expect(mainLine).toContain('extends (parent: sw-button)');
    });

    it('report includes a ⚠ warning line with the parent name and README reference', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        const warnLine = report.find((l) => l.includes('⚠'));
        expect(warnLine).toBeDefined();
        expect(warnLine).toContain('sw-button');
        expect(warnLine).toContain('README.md');
    });

    it('warning line appears after the partially-migrated line in the report', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        const mainIdx = report.findIndex((l) => l.includes('partially-migrated'));
        const warnIdx = report.findIndex((l) => l.includes('⚠'));
        expect(mainIdx).toBeGreaterThanOrEqual(0);
        expect(warnIdx).toBeGreaterThan(mainIdx);
    });

    it('does not increment extendsComponents for a mixins-only component', () => {
        const mixinDir = createTempDir();
        makeComponent(mixinDir, 'sw-mixin-list', readFixture('mixin-component.index.js'), '<div/>');
        const { stats } = runMigration(mixinDir, { dryRun: true });
        expect(stats.extendsComponents).toBe(0);
        rmSync(mixinDir, { recursive: true, force: true });
    });
});

describe('runMigration — delete-originals (fully-migrated)', () => {
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

    it('replaces index.js with an SFC entry point and deletes .html.twig after writing the .vue file', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'index.js'))).toBe(true);
        expect(existsSync(join(componentDir, 'sw-simple-card.html.twig'))).toBe(false);
    });

    it('keeps directory imports working through the generated index.js entry point', () => {
        writeFileSync(join(tmpDir, 'consumer.js'), "import './sw-simple-card';\n", 'utf-8');

        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });

        const entrypoint = readFileSync(join(componentDir, 'index.js'), 'utf-8');
        expect(entrypoint).toBe(
            "import component from './sw-simple-card.vue';\n\nShopware.Component.register('sw-simple-card', component);\n",
        );
    });

    it('writes the .vue file before deleting originals', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'sw-simple-card.vue'))).toBe(true);
    });

    it('increments deletedOriginals stat', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(stats.deletedOriginals).toBe(1);
        expect(stats.fullyMigrated).toBe(1);
    });

    it('report includes entrypoint replacement and twig deletion lines', () => {
        const { report } = runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(report.some((l) => l.includes('replaced entrypoint') && l.includes('index.js'))).toBe(true);
        expect(report.some((l) => l.includes('deleted original') && l.includes('.html.twig'))).toBe(true);
    });

    it('does not delete originals in dry-run mode even when deleteOriginals is true', () => {
        runMigration(tmpDir, { dryRun: true, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'index.js'))).toBe(true);
        expect(existsSync(join(componentDir, 'sw-simple-card.html.twig'))).toBe(true);
    });

    it('does not delete originals when deleteOriginals is false', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: false });
        expect(existsSync(join(componentDir, 'index.js'))).toBe(true);
        expect(existsSync(join(componentDir, 'sw-simple-card.html.twig'))).toBe(true);
    });

    it('deletedOriginals stat is 0 when deleteOriginals is false', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false, deleteOriginals: false });
        expect(stats.deletedOriginals).toBe(0);
    });
});

describe('runMigration — delete-originals (partially-migrated)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-mixin-list',
            readFixture('mixin-component.index.js'),
            '<div class="sw-mixin-list"></div>',
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('keeps originals for a partially-migrated component', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'sw-mixin-list.html.twig'))).toBe(true);
        expect(existsSync(join(componentDir, 'sw-mixin-list.vue'))).toBe(true);

        const entrypoint = readFileSync(join(componentDir, 'index.js'), 'utf-8');
        expect(entrypoint).toBe(readFixture('mixin-component.index.js'));
    });

    it('does not increment deletedOriginals stat for partially-migrated component', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(stats.deletedOriginals).toBe(0);
        expect(stats.partiallyMigrated).toBe(1);
    });

    it('reports that originals were kept for manual follow-up', () => {
        const { report } = runMigration(tmpDir, { dryRun: false, deleteOriginals: true });

        expect(report).toContain(
            '   ⚠  kept originals because partial migration requires manual follow-up before replacing the entrypoint',
        );
    });
});

describe('runMigration — $el warning', () => {
    let tmpDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        makeComponent(
            tmpDir,
            'sw-composables',
            readFixture('composables-component.index.js'),
            readFixture('composables-component.html.twig'),
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('increments elWarnings stat for a component that uses $el', () => {
        const { stats } = runMigration(tmpDir, { dryRun: true });
        expect(stats.elWarnings).toBe(1);
    });

    it('does not increment elWarnings for a component without $el', () => {
        const cleanDir = createTempDir();
        makeComponent(
            cleanDir,
            'sw-simple-card',
            readFixture('simple-component.index.js'),
            readFixture('simple-component.html.twig'),
        );
        const { stats } = runMigration(cleanDir, { dryRun: true });
        expect(stats.elWarnings).toBe(0);
        rmSync(cleanDir, { recursive: true, force: true });
    });

    it('report includes a ⚠ warning line after the component line', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        const warnLine = report.find((l) => l.includes('⚠'));
        expect(warnLine).toBeDefined();
        expect(warnLine).toContain('$el usage detected');
    });

    it('warning line appears after the fully-migrated line in the report', () => {
        const { report } = runMigration(tmpDir, { dryRun: true });
        const migratedIdx = report.findIndex((l) => l.includes('fully-migrated'));
        const warnIdx = report.findIndex((l) => l.includes('⚠'));
        expect(migratedIdx).toBeGreaterThanOrEqual(0);
        expect(warnIdx).toBeGreaterThan(migratedIdx);
    });
});

describe('runMigration — delete-originals (not-migratable)', () => {
    let tmpDir: string;
    let componentDir: string;

    beforeEach(() => {
        tmpDir = createTempDir();
        componentDir = makeComponent(
            tmpDir,
            'sw-render-component',
            readFixture('render-component.index.js'),
            '<div class="sw-render-component"></div>',
        );
    });

    afterEach(() => {
        rmSync(tmpDir, { recursive: true, force: true });
    });

    it('never deletes originals for a not-migratable component', () => {
        runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(existsSync(join(componentDir, 'index.js'))).toBe(true);
        expect(existsSync(join(componentDir, 'sw-render-component.html.twig'))).toBe(true);
    });

    it('deletedOriginals stat remains 0 for not-migratable component', () => {
        const { stats } = runMigration(tmpDir, { dryRun: false, deleteOriginals: true });
        expect(stats.deletedOriginals).toBe(0);
    });
});
