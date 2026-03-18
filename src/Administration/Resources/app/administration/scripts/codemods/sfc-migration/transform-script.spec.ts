import path from 'path';
import fs from 'fs';
import { transformScript } from './transform-script';

const fixturesDir = path.join(__dirname, '__fixtures__');

function readFixture(name: string): string {
    return fs.readFileSync(path.join(fixturesDir, name), 'utf8');
}

/**
 * Integrative tests for transformScript().
 *
 * Each test provides a complete index.js file and asserts that the entire
 * resulting script block is correct — every Options API section (inject, data,
 * computed, methods, watch, lifecycle) is correctly converted in one pass.
 */
describe('scripts/codemods/sfc-migration/transform-script', () => {
    describe('simple-component: fully converts inject, data, computed, and methods to Composition API', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('simple-component.index.js'));
        });

        it('reports status fully-migratable with no blockers', () => {
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('produces a <script setup> script type', () => {
            expect(result.scriptType).toBe('setup');
        });

        it('contains a single Vue import statement with all required composables', () => {
            expect(result.script).toMatch(/import\s*\{[^}]*\}\s*from\s*['"]vue['"]/);
            expect(result.script).toContain('ref');
            expect(result.script).toContain('computed');
            expect(result.script).toContain('inject');
        });

        it('converts inject to an inject() call', () => {
            expect(result.script).toContain("const repositoryFactory = inject('repositoryFactory');");
        });

        it('converts data() — title string to ref(), isLoading boolean to ref()', () => {
            expect(result.script).toContain("const title = ref('Default Title');");
            expect(result.script).toContain('const isLoading = ref(false);');
        });

        it('converts the computed getter to computed()', () => {
            expect(result.script).toContain('const description = computed(');
        });

        it('converts the onSave method to a function declaration', () => {
            expect(result.script).toContain('const onSave =');
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    describe('block-component: fully converts inject, data, computed getter+setter, watch, methods, and lifecycle hook', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('block-component.index.js'));
        });

        it('reports status fully-migratable with no blockers', () => {
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('produces a <script setup> script type', () => {
            expect(result.scriptType).toBe('setup');
        });

        it('converts inject to an inject() call', () => {
            expect(result.script).toContain("const acl = inject('acl');");
        });

        it('converts all three data() properties to ref() declarations', () => {
            expect(result.script).toContain("const title = ref('Block Card');");
            expect(result.script).toContain("const description = ref('A card with extensible blocks');");
            expect(result.script).toContain('const count = ref(0);');
        });

        it('converts the plain computed getter (canEdit) to computed()', () => {
            expect(result.script).toContain('const canEdit = computed(');
        });

        it('converts the getter+setter computed (label) to computed({ get, set })', () => {
            expect(result.script).toContain('const label = computed({');
            expect(result.script).toContain('get:');
            expect(result.script).toContain('set:');
        });

        it('converts the count watcher to a watch() call', () => {
            expect(result.script).toContain('watch(');
            expect(result.script).toContain('count');
        });

        it('converts the onAction method to a function declaration', () => {
            expect(result.script).toContain('const onAction =');
        });

        it('converts the mounted() lifecycle hook to onMounted()', () => {
            expect(result.script).toContain('onMounted(');
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    describe('mixin-component: detects mixins as a blocker and falls back to Options API', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('mixin-component.index.js'));
        });

        it('reports status partially-migratable', () => {
            expect(result.status).toBe('partially-migratable');
        });

        it('lists mixins as the blocker', () => {
            expect(result.blockers).toContain('mixins');
        });

        it('produces an options script type (backoff)', () => {
            expect(result.scriptType).toBe('options');
        });

        it('preserves the original component registration intact in the script output', () => {
            expect(result.script).toContain('sw-mixin-list');
            expect(result.script).toContain('mixins:');
            expect(result.script).toContain('loadItems');
        });

        it('does not produce any Composition API ref() or computed() calls', () => {
            expect(result.script).not.toContain('= ref(');
            expect(result.script).not.toContain('= computed(');
        });

        it('matches the complete Options API backoff script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    describe('render-component: detects render() as a hard blocker and marks as not-migratable', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('render-component.index.js'));
        });

        it('reports status not-migratable', () => {
            expect(result.status).toBe('not-migratable');
        });

        it('lists render function as the blocker', () => {
            expect(result.blockers).toContain('render function');
        });

        it('produces an empty script string — no output is generated for non-migratable components', () => {
            expect(result.script).toBe('');
        });
    });
});
