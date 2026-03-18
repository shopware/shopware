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
 * the entire resulting .vue SFC is structurally correct — template, script,
 * imports, and component name — in one end-to-end pass.
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

        it('imports the required Composition API composables from vue', () => {
            expect(result.sfc).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('contains the component name sw-simple-card in the script', () => {
            expect(result.sfc).toContain('sw-simple-card');
        });

        it('converts inject, data, computed, and methods — all in the single SFC output', () => {
            expect(result.sfc).toContain("inject('repositoryFactory')");
            expect(result.sfc).toContain("ref('Default Title')");
            expect(result.sfc).toContain('ref(false)');
            expect(result.sfc).toContain('computed(');
            expect(result.sfc).toContain('onSave');
        });

        it('places <template> before <script setup> in the file', () => {
            expect(result.sfc.indexOf('<template>')).toBeLessThan(result.sfc.indexOf('<script setup>'));
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('block-component: fully migrated SFC with twig blocks replaced and full Composition API script', () => {
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

        it('converts inject, data (3 properties), computed getter, computed getter+setter, watch, method, and lifecycle hook — all present', () => {
            expect(result.sfc).toContain("inject('acl')");
            expect(result.sfc).toContain("ref('Block Card')");
            expect(result.sfc).toContain("ref('A card with extensible blocks')");
            expect(result.sfc).toContain('ref(0)');
            expect(result.sfc).toContain('const canEdit = computed(');
            expect(result.sfc).toContain('const label = computed({');
            expect(result.sfc).toContain('watch(');
            expect(result.sfc).toContain('const onAction =');
            expect(result.sfc).toContain('onMounted(');
        });

        it('produces a <script setup> section', () => {
            expect(result.sfc).toContain('<script setup>');
            expect(result.sfc).not.toContain('<script>');
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('mixin-component: partially migrated SFC — template converted, script kept as Options API', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            // Mixin fixture has no twig template file; use a plain template
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

        it('preserves the full Options API component definition intact in the script', () => {
            expect(result.sfc).toContain('sw-mixin-list');
            expect(result.sfc).toContain('mixins:');
            expect(result.sfc).toContain('loadItems');
            expect(result.sfc).toContain('onNotify');
        });

        it('wraps the template in a <template> section', () => {
            expect(result.sfc).toContain('<template>');
            expect(result.sfc).toContain('class="sw-mixin-list"');
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

        it('reports status not-migratable', () => {
            expect(result.status).toBe('not-migratable');
        });

        it('lists render function as the blocker', () => {
            expect(result.blockers).toContain('render function');
        });

        it('produces an empty SFC string — nothing is written to disk for this component', () => {
            expect(result.sfc).toBe('');
        });
    });
});
