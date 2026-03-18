import path from 'path';
import fs from 'fs';
import { mergeComponentFiles } from './generate-sfc';

const fixturesDir = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return fs.readFileSync(path.join(fixturesDir, name), 'utf8');
}

/**
 * Integrative tests for mergeComponentFiles().
 *
 * Each test provides a complete .html.twig + index.js pair and asserts that
 * the entire resulting .vue SFC is structurally correct in one end-to-end pass.
 *
 * Fully-migrated components wrap all their state in createExtendableSetup() so
 * they remain extensible via overrideComponentSetup() — exactly as specified by
 * the composition extension system (composition-extension-system.ts).
 */
describe('scripts/codemods/sfc-migration/generate-sfc', () => {
    describe('simple-component: fully migrated SFC with plain template and <script setup>', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('simple-component.html.twig'),
                readFixture('simple-component.index.js'),
            );
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('produces a <template> section with the original HTML preserved', () => {
            expect(result.sfc).toContain('<template>');
            expect(result.sfc).toContain('</template>');
            expect(result.sfc).toContain('class="sw-simple-card"');
            expect(result.sfc).toContain('@click="onSave"');
        });

        it('produces a <script setup> section (not a plain <script>)', () => {
            expect(result.sfc).toContain('<script setup>');
            expect(result.sfc).not.toContain('<script>');
        });

        it('imports createExtendableSetup from the composition extension system', () => {
            expect(result.sfc).toContain(
                "import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';",
            );
        });

        it('imports the required Vue composables from vue', () => {
            expect(result.sfc).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('wraps all state in createExtendableSetup with the component name "sw-simple-card"', () => {
            expect(result.sfc).toContain('createExtendableSetup(');
            expect(result.sfc).toContain("name: 'sw-simple-card'");
        });

        it('declares inject, data, computed, and method state inside the createExtendableSetup callback', () => {
            const setupStart = result.sfc.indexOf('createExtendableSetup(');
            expect(result.sfc.indexOf("inject('repositoryFactory')")).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf("ref('Default Title')")).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf('ref(false)')).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf('computed(')).toBeGreaterThan(setupStart);
        });

        it('returns state under a public: key and destructures the result for template access', () => {
            expect(result.sfc).toContain('public:');
            expect(result.sfc).toMatch(/const\s*\{[^}]*\}\s*=\s*createExtendableSetup\s*\(/);
        });

        it('places <template> before <script setup> in the file', () => {
            expect(result.sfc.indexOf('<template>')).toBeLessThan(result.sfc.indexOf('<script setup>'));
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('block-component: fully migrated SFC with twig blocks replaced and createExtendableSetup script', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('block-component.html.twig'),
                readFixture('block-component.index.js'),
            );
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('replaces all twig block syntax with <sw-block> components in the <template> section', () => {
            expect(result.sfc).toContain('<sw-block name="sw_block_card" :data="$dataScope">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_header" :data="$dataScope">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_content" :data="$dataScope">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_footer" :data="$dataScope">');
            expect(result.sfc).toContain('<sw-block-parent/>');
            expect(result.sfc).not.toContain('{%');
            expect(result.sfc).not.toContain('%}');
        });

        it('wraps all state in createExtendableSetup with the component name "sw-block-card"', () => {
            expect(result.sfc).toContain('createExtendableSetup(');
            expect(result.sfc).toContain("name: 'sw-block-card'");
        });

        it('declares inject, all data refs, computed properties, watch, method, and lifecycle hook inside the callback', () => {
            const setupStart = result.sfc.indexOf('createExtendableSetup(');
            expect(result.sfc.indexOf("inject('acl')")).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf("ref('Block Card')")).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf('computed(')).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf('watch(')).toBeGreaterThan(setupStart);
            expect(result.sfc.indexOf('onMounted(')).toBeGreaterThan(setupStart);
        });

        it('returns state under a public: key', () => {
            expect(result.sfc).toContain('public:');
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('mixin-component: partially migrated SFC — template converted, script kept as Options API without createExtendableSetup', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                '<div class="sw-mixin-list"></div>',
                readFixture('mixin-component.index.js'),
            );
        });

        it('reports status partially-migrated with mixins listed as a blocker', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain('mixins');
        });

        it('produces a plain <script> block (not <script setup>) as Options API backoff', () => {
            expect(result.sfc).toContain('<script>');
            expect(result.sfc).not.toContain('<script setup>');
        });

        it('does not use createExtendableSetup — backoff components remain as-is for manual migration', () => {
            expect(result.sfc).not.toContain('createExtendableSetup');
        });

        it('preserves the full Options API component definition intact in the script', () => {
            expect(result.sfc).toContain('sw-mixin-list');
            expect(result.sfc).toContain('mixins:');
            expect(result.sfc).toContain('loadItems');
            expect(result.sfc).toContain('onNotify');
        });

        it('matches the complete partially-migrated SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('render-component: not migratable — no SFC is produced', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                '',
                readFixture('render-component.index.js'),
            );
        });

        it('reports status not-migratable with render function as the blocker', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('render function');
        });

        it('produces an empty SFC string — nothing is written to disk for this component', () => {
            expect(result.sfc).toBe('');
        });
    });
});
