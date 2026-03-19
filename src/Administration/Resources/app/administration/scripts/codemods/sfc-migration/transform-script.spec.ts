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
 * Each test suite provides a complete index.js file and asserts that the entire
 * resulting script block is correct — covering defineProps, defineEmits,
 * this-rewriting, watch sources, lifecycle hooks, and module-level code.
 */
describe('scripts/codemods/sfc-migration/transform-script', () => {
    // -------------------------------------------------------------------------
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

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('block-component: converts props, emits, data init from prop, computed, watch (prop+data), methods with $emit and $refs, and lifecycle', () => {
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

        it('emits defineProps with the correct prop names', () => {
            expect(result.script).toContain('const props = defineProps(');
            expect(result.script).toContain('initialCount');
            expect(result.script).toContain('readOnly');
        });

        it('emits defineEmits with the action and reset events', () => {
            expect(result.script).toContain("const emit = defineEmits([");
            expect(result.script).toContain("'action'");
            expect(result.script).toContain("'reset'");
        });

        it('rewrites data initializer this.initialCount → props.initialCount', () => {
            expect(result.script).toContain('ref(props.initialCount)');
        });

        it('rewrites this.$emit → emit in methods', () => {
            expect(result.script).toContain("emit('action'");
            expect(result.script).toContain("emit('reset'");
            expect(result.script).not.toMatch(/\bthis\.\$emit\b/);
        });

        it('rewrites this.$refs.cardWrapper → cardWrapper.value', () => {
            expect(result.script).toContain('cardWrapper.value.focus()');
            expect(result.script).not.toMatch(/\bthis\.\$refs\b/);
        });

        it('declares a template ref for cardWrapper', () => {
            expect(result.script).toContain('const cardWrapper = ref(null)');
        });

        it('uses props.readOnly as watch source for a prop watcher', () => {
            expect(result.script).toContain('watch(() => props.readOnly,');
        });

        it('uses count.value as watch source for a data ref watcher', () => {
            expect(result.script).toContain('watch(() => count.value,');
        });

        it('rewrites this.count → count.value inside method and watch bodies', () => {
            expect(result.script).toContain('count.value += 1');
            expect(result.script).not.toMatch(/\bthis\.count\b/);
        });

        it('rewrites this.initialCount → props.initialCount inside method body', () => {
            expect(result.script).toMatch(/props\.initialCount/);
            expect(result.script).not.toMatch(/\bthis\.initialCount\b/);
        });

        it('rewrites this.readOnly → props.readOnly in computed body', () => {
            expect(result.script).toContain('props.readOnly');
            expect(result.script).not.toMatch(/\bthis\.readOnly\b/);
        });

        it('exposes the getter+setter computed (label) as computed({ get, set })', () => {
            expect(result.script).toContain('const label = computed({');
            expect(result.script).toContain('get:');
            expect(result.script).toContain('set:');
        });

        it('rewrites this.title → title.value in getter/setter bodies', () => {
            expect(result.script).toContain('return title.value');
            expect(result.script).toContain('title.value = val');
        });

        it('wires mounted() to onMounted()', () => {
            expect(result.script).toContain('onMounted(');
        });

        it('does not contain any this. references in the output', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('created-component: created() runs as direct setup code; beforeUnmount/unmounted use correct hooks', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('created-component.index.js'));
        });

        it('reports status fully-migratable', () => {
            expect(result.status).toBe('fully-migratable');
        });

        it('emits defineProps and defineEmits', () => {
            expect(result.script).toContain('const props = defineProps(');
            expect(result.script).toContain("const emit = defineEmits([");
            expect(result.script).toContain("'ready'");
        });

        it('places the created() body inside createExtendableSetup callback (before onMounted), giving it access to inject values', () => {
            // The shortcutService.stopEventListener() call should appear inside
            // the createExtendableSetup() callback, before the onMounted call
            const stopListenerPos = result.script.indexOf('shortcutService.stopEventListener()');
            const onMountedPos = result.script.indexOf('onMounted(');
            expect(stopListenerPos).toBeGreaterThan(-1);
            expect(stopListenerPos).toBeLessThan(onMountedPos);
        });

        it('does NOT wrap the created() body in onMounted()', () => {
            // onMounted should only appear for the actual mounted() hook
            const onMountedCount = (result.script.match(/onMounted\(/g) ?? []).length;
            expect(onMountedCount).toBe(1);
        });

        it('maps mounted() to onMounted()', () => {
            expect(result.script).toContain('onMounted(');
        });

        it('maps beforeUnmount() to onBeforeUnmount()', () => {
            expect(result.script).toContain('onBeforeUnmount(');
        });

        it('maps unmounted() to onUnmounted()', () => {
            expect(result.script).toContain('onUnmounted(');
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('module-level-component: preserves module-level code (scss import, const declarations)', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('module-level-component.index.js'));
        });

        it('reports status fully-migratable', () => {
            expect(result.status).toBe('fully-migratable');
        });

        it('includes the scss side-effect import', () => {
            expect(result.script).toContain("import './module-level-component.scss'");
        });

        it('includes the cloneDeep destructure declaration', () => {
            expect(result.script).toContain('const { cloneDeep } = Shopware.Utils.object');
        });

        it('includes the COLORS array declaration', () => {
            expect(result.script).toContain('const COLORS =');
        });

        it('does NOT include the template import', () => {
            expect(result.script).not.toContain("import template from");
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
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

    // -------------------------------------------------------------------------
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
