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
 *
 * Migrated components wrap all state in createExtendableSetup() so they remain
 * extensible via overrideComponentSetup() after migration.
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

        it('imports createExtendableSetup from the composition extension system', () => {
            expect(result.script).toContain(
                "import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';",
            );
        });

        it('imports the required Vue composables from vue', () => {
            expect(result.script).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
            expect(result.script).toContain('computed');
            expect(result.script).toContain('inject');
        });

        it('passes the component name "sw-simple-card" to createExtendableSetup', () => {
            expect(result.script).toContain("name: 'sw-simple-card'");
        });

        it('declares inject, data, computed, and method state inside the createExtendableSetup callback', () => {
            const setupCallbackStart = result.script.indexOf('createExtendableSetup(');
            expect(result.script.indexOf("inject('repositoryFactory')")).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf("ref('Default Title')")).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('ref(false)')).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('computed(')).toBeGreaterThan(setupCallbackStart);
        });

        it('returns state under the public: key inside the createExtendableSetup callback', () => {
            expect(result.script).toContain('public:');
            expect(result.script).toContain('repositoryFactory');
            expect(result.script).toContain('title');
            expect(result.script).toContain('isLoading');
            expect(result.script).toContain('description');
            expect(result.script).toContain('onSave');
        });

        it('destructures the createExtendableSetup result at the top level', () => {
            expect(result.script).toMatch(/const\s*\{[^}]*\}\s*=\s*createExtendableSetup\s*\(/);
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

        it('imports createExtendableSetup from the composition extension system', () => {
            expect(result.script).toContain(
                "import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';",
            );
        });

        it('passes the component name "sw-block-card" to createExtendableSetup', () => {
            expect(result.script).toContain("name: 'sw-block-card'");
        });

        it('declares all state inside the createExtendableSetup callback — inject, three data refs, two computed, watch, method, and lifecycle hook', () => {
            const setupCallbackStart = result.script.indexOf('createExtendableSetup(');

            expect(result.script.indexOf("inject('acl')")).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf("ref('Block Card')")).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf("ref('A card with extensible blocks')")).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('ref(0)')).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('computed(')).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('watch(')).toBeGreaterThan(setupCallbackStart);
            expect(result.script.indexOf('onMounted(')).toBeGreaterThan(setupCallbackStart);
        });

        it('exposes the getter+setter computed (label) as computed({ get, set }) in the public return', () => {
            expect(result.script).toContain('const label = computed({');
            expect(result.script).toContain('get:');
            expect(result.script).toContain('set:');
        });

        it('returns all state under the public: key', () => {
            expect(result.script).toContain('public:');
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

        it('reports status partially-migratable with mixins as the blocker', () => {
            expect(result.status).toBe('partially-migratable');
            expect(result.blockers).toContain('mixins');
        });

        it('produces an options script type (backoff — no createExtendableSetup)', () => {
            expect(result.scriptType).toBe('options');
            expect(result.script).not.toContain('createExtendableSetup');
        });

        it('preserves the original Options API component registration intact', () => {
            expect(result.script).toContain('sw-mixin-list');
            expect(result.script).toContain('mixins:');
            expect(result.script).toContain('loadItems');
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

        it('reports status not-migratable with render function as the blocker', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('render function');
        });

        it('produces an empty script string — no output is generated for non-migratable components', () => {
            expect(result.script).toBe('');
        });
    });
});
