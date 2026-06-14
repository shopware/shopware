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
            expect(result.script).toContain('const emit = defineEmits([');
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
            expect(result.script).toContain('const emit = defineEmits([');
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
    describe('async-lifecycle-component: preserves async lifecycle hook bodies', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('async-lifecycle-component.index.js'));
        });

        it('reports status fully-migratable', () => {
            expect(result.status).toBe('fully-migratable');
        });

        it('emits async callbacks for Composition API lifecycle hooks', () => {
            expect(result.script).toContain('onMounted(async () => {');
            expect(result.script).toContain('await loadData();');
        });

        it('wraps async created() logic in an async setup IIFE', () => {
            expect(result.script).toContain('void (async () => {');
            expect(result.script).toContain('await bootstrap();');
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
            expect(result.script).not.toContain('import template from');
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

        it('removes the template import and stale top-level template option from backoff output', () => {
            expect(result.script).not.toContain('import template from');
            expect(result.script).not.toMatch(/^[\t ]*template,?$/m);
            expect(result.script).not.toMatch(/^[\t ]*template\s*:/m);
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

    // -------------------------------------------------------------------------
    describe('composables-component: rewrites $router, $route, $slots, $nextTick, $t, $tc, and $el to their Composition API equivalents', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('composables-component.index.js'));
        });

        it('reports status fully-migratable with no blockers', () => {
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('produces a <script setup> script type', () => {
            expect(result.scriptType).toBe('setup');
        });

        it('rewrites this.$router → router and imports useRouter from vue-router', () => {
            expect(result.script).toContain('router.back()');
            expect(result.script).not.toMatch(/\bthis\.\$router\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*useRouter[^}]*\}\s*from\s*['"]vue-router['"]/);
        });

        it('rewrites this.$route → route and imports useRoute from vue-router', () => {
            expect(result.script).toContain('route.name');
            expect(result.script).not.toMatch(/\bthis\.\$route\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*useRoute[^}]*\}\s*from\s*['"]vue-router['"]/);
        });

        it('rewrites this.$slots → slots and imports useSlots from vue', () => {
            expect(result.script).toContain('slots.default');
            expect(result.script).not.toMatch(/\bthis\.\$slots\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*useSlots[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('rewrites this.$nextTick → nextTick and imports nextTick from vue', () => {
            expect(result.script).toContain('await nextTick()');
            expect(result.script).not.toMatch(/\bthis\.\$nextTick\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*nextTick[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('rewrites this.$tc and this.$t to t, and calls useI18n()', () => {
            expect(result.script).toContain("t('sw.composables.label', 2)");
            expect(result.script).toContain("t('sw.composables.title')");
            expect(result.script).not.toMatch(/\bthis\.\$tc\b/);
            expect(result.script).not.toMatch(/\bthis\.\$t\b/);
            expect(result.script).toContain('useI18n()');
        });

        it('rewrites this.$el → getCurrentInstance()?.proxy?.$el with a TODO comment', () => {
            expect(result.script).toContain('/* TODO: $el */ getCurrentInstance()?.proxy?.$el');
            expect(result.script).not.toMatch(/\bthis\.\$el\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*getCurrentInstance[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('inherit-attrs-component: emits defineOptions({ inheritAttrs: false }) and excludes inheritAttrs from the options object', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('inherit-attrs-component.index.js'));
        });

        it('reports status fully-migratable with no blockers', () => {
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('produces a <script setup> script type', () => {
            expect(result.scriptType).toBe('setup');
        });

        it('emits defineOptions({ inheritAttrs: false }) at the top of the script', () => {
            expect(result.script).toContain('defineOptions({ inheritAttrs: false })');
        });

        it('does not leave an inheritAttrs key inside the createExtendableSetup call', () => {
            const setupStart = result.script.indexOf('createExtendableSetup(');
            expect(setupStart).toBeGreaterThan(-1);
            const afterSetup = result.script.slice(setupStart);
            expect(afterSetup).not.toContain('inheritAttrs:');
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('composables-component: $attrs → attrs from useAttrs()', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('composables-component.index.js'));
        });

        it('rewrites this.$attrs → attrs in method bodies', () => {
            expect(result.script).toContain('attrs.class');
            expect(result.script).not.toMatch(/\bthis\.\$attrs\b/);
        });

        it('imports useAttrs from vue', () => {
            expect(result.script).toMatch(/import\s*\{[^}]*useAttrs[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('declares const attrs = useAttrs()', () => {
            expect(result.script).toContain('const attrs = useAttrs();');
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });
    });

    // -------------------------------------------------------------------------
    describe('debounce-component: property-assignment methods (debounce wrappers) are preserved and this-rewritten', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('debounce-component.index.js'));
        });

        it('reports status fully-migratable with no blockers', () => {
            expect(result.status).toBe('fully-migratable');
            expect(result.blockers).toEqual([]);
        });

        it('emits the debounced method as a const assignment preserving the debounce wrapper', () => {
            expect(result.script).toContain('const searchDebounce = debounce(');
        });

        it('rewrites this.doSearch() inside the debounce callback', () => {
            expect(result.script).toContain('doSearch()');
            expect(result.script).not.toMatch(/\bthis\.doSearch\b/);
        });

        it('includes searchDebounce in the public: return', () => {
            const publicStart = result.script.indexOf('public:');
            expect(publicStart).toBeGreaterThan(-1);
            expect(result.script.slice(publicStart)).toContain('searchDebounce');
        });

        it('rewrites this.searchDebounce() in the onInput method', () => {
            expect(result.script).toContain('searchDebounce()');
            expect(result.script).not.toMatch(/\bthis\.searchDebounce\b/);
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('extend-component: Shopware.Component.extend() triggers the partially-migratable soft blocker', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('extend-component.index.js'));
        });

        it('reports status partially-migratable', () => {
            expect(result.status).toBe('partially-migratable');
        });

        it('lists extends with parent component name as a blocker', () => {
            expect(result.blockers).toContain('extends (parent: sw-button)');
        });

        it('produces an options script type (backoff — no createExtendableSetup)', () => {
            expect(result.scriptType).toBe('options');
            expect(result.script).not.toContain('createExtendableSetup');
        });

        it('preserves the original Shopware.Component.extend() registration intact', () => {
            expect(result.script).toContain('sw-extended-button');
            expect(result.script).toContain('sw-button');
            expect(result.script).toContain('extraLabel');
            expect(result.script).toContain('getLabel');
        });

        it('matches the complete Options API backoff script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('watch object form with deep option', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() { return { items: [], count: 0 }; },
                watch: {
                    items: {
                        handler(newItems) { this.count = newItems.length; },
                        deep: true,
                        immediate: true,
                    }
                },
            });`;
            result = transformScript(js);
        });

        it('generates watch() call with deep/immediate options', () => {
            expect(result.script).toContain('watch(() => items.value, (newItems) => {');
            expect(result.script).toContain('}, { deep: true, immediate: true });');
        });

        it('rewrites this.count inside handler', () => {
            expect(result.script).not.toContain('this.count');
        });
    });

    // -------------------------------------------------------------------------
    describe('watch object form with string handler', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() { return { items: [], count: 0 }; },
                watch: {
                    items: {
                        handler: 'updateCount',
                        deep: true,
                        immediate: true,
                    }
                },
                methods: {
                    updateCount(newItems) { this.count = newItems.length; }
                },
            });`;
            result = transformScript(js);
        });

        it('generates the delegated method and watch registration without a manual TODO fallback', () => {
            expect(result.script).toContain('const updateCount = (newItems) => {');
            expect(result.script).toContain(
                'watch(() => items.value, (...args) => updateCount(...args), { deep: true, immediate: true });',
            );
            expect(result.script).not.toContain('TODO: migrate watch entry manually');
        });

        it('generates a delegated watch() call preserving deep/immediate', () => {
            expect(result.script).toContain(
                'watch(() => items.value, (...args) => updateCount(...args), { deep: true, immediate: true });',
            );
        });

        it('rewrites this.count inside the generated method', () => {
            expect(result.script).toContain('count.value = newItems.length');
            expect(result.script).not.toContain('this.count');
        });
    });

    // -------------------------------------------------------------------------
    describe('watch direct string handler form', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() { return { items: [], count: 0 }; },
                watch: {
                    items: 'updateCount'
                },
                methods: {
                    updateCount(newItems) { this.count = newItems.length; }
                },
            });`;
            result = transformScript(js);
        });

        it('delegates the direct string handler to the converted method', () => {
            expect(result.script).toContain('watch(() => items.value, (...args) => updateCount(...args));');
            expect(result.script).toContain('const updateCount = (newItems) => {');
        });

        it('rewrites method body references used by the delegated string handler', () => {
            expect(result.script).toContain('count.value = newItems.length');
            expect(result.script).not.toContain('this.count');
        });
    });

    // -------------------------------------------------------------------------
    it('surfaces unsupported shorthand and spread data entries with TODO comments', () => {
        const js = `const title = 'External title';
        const args = { count: 1 };

        Shopware.Component.register('sw-test', {
            template,
            data() {
                return {
                    title,
                    ...args,
                    regular: 'kept',
                };
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('data: title: shorthand data entries must be migrated manually');
        expect(result.blockers).toContain('data: ...args: spread data entries must be migrated manually');
        expect(result.script).toContain(
            'TODO: migrate data entry manually: data: title: shorthand data entries must be migrated manually',
        );
        expect(result.script).toContain(
            'TODO: migrate data entry manually: data: ...args: spread data entries must be migrated manually',
        );
        expect(result.script).toContain("const regular = ref('kept');");
    });

    // -------------------------------------------------------------------------
    it('surfaces unsupported shorthand and spread method entries with TODO comments', () => {
        const js = `const sharedMethods = {};
        const shorthandMethod = () => 'external';

        Shopware.Component.register('sw-test', {
            template,
            methods: {
                ...sharedMethods,
                shorthandMethod,
                kept() {
                    return 'kept';
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('methods: ...sharedMethods: spread method entries must be migrated manually');
        expect(result.blockers).toContain(
            'methods: shorthandMethod: shorthand method entries must be migrated manually',
        );
        expect(result.script).toContain(
            'TODO: migrate method manually: methods: ...sharedMethods: spread method entries must be migrated manually',
        );
        expect(result.script).toContain(
            'TODO: migrate method manually: methods: shorthandMethod: shorthand method entries must be migrated manually',
        );
        expect(result.script).toContain('const kept = () => {');
    });

    // -------------------------------------------------------------------------
    it('surfaces unsupported watch entries with a TODO comment instead of silently dropping them', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                items: {
                    handler: externalHandler,
                }
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('watch: items: unsupported watcher handler shape');
        expect(result.script).toContain('TODO: migrate watch entry manually: items: unsupported watcher handler shape');
    });

    // -------------------------------------------------------------------------
    it('marks top-level non-object watch definitions as partially migratable', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: externalWatchers,
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('watch: watch must be an object literal');
        expect(result.script).toContain('TODO: migrate watch entry manually: watch must be an object literal');
        expect(result.script).not.toContain("import { watch } from 'vue';");
    });

    // -------------------------------------------------------------------------
    it('surfaces unsupported non-object watcher definitions with a TODO comment', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                items: externalHandler,
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain('TODO: migrate watch entry manually: items: unsupported watcher definition');
        expect(result.script).not.toContain("import { watch } from 'vue';");
    });

    // -------------------------------------------------------------------------
    it('surfaces unsupported watch spread entries with a TODO comment', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                ...externalWatchers,
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain(
            'TODO: migrate watch entry manually: ...externalWatchers: unsupported watcher entry',
        );
        expect(result.script).not.toContain("import { watch } from 'vue';");
    });

    // -------------------------------------------------------------------------
    it('sanitizes multiline unsupported watcher entries before emitting TODO comments', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                ...buildWatchers(
                    foo,
                    bar,
                ),
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toMatch(
            /TODO: migrate watch entry manually: \.{3}buildWatchers\( foo, bar, \): unsupported watcher entry/,
        );
        expect(result.script).not.toMatch(/TODO: migrate watch entry manually:[^\n]*\n\s*foo/);
    });

    // -------------------------------------------------------------------------
    it('surfaces nested watch paths with a TODO comment instead of generating an invalid source', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                'items.length': 'updateCount'
            },
            methods: {
                updateCount() {},
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain(
            'TODO: migrate watch entry manually: items.length: nested watch paths are not supported',
        );
        expect(result.script).not.toContain('watch(() => items.length.value');
    });

    // -------------------------------------------------------------------------
    it('falls back to a manual TODO when watch targets are not valid identifiers', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                'item-count': 'updateCount'
            },
            methods: {
                updateCount() {},
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain(
            'watch: item-count: watch targets that are not valid identifiers must be migrated manually',
        );
        expect(result.script).toContain(
            'TODO: migrate watch entry manually: item-count: watch targets that are not valid identifiers must be migrated manually',
        );
        expect(result.script).not.toContain('watch(() => item-count.value');
    });

    // -------------------------------------------------------------------------
    it('uses bracket access for quoted prop watch targets', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            props: {
                'item-count': {
                    type: Number,
                    required: false,
                },
            },
            watch: {
                'item-count': 'updateCount'
            },
            methods: {
                updateCount() {},
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.blockers).not.toContain(
            'watch: item-count: watch targets that are not valid identifiers must be migrated manually',
        );
        expect(result.script).toContain("watch(() => props['item-count'], (...args) => updateCount(...args));");
    });

    // -------------------------------------------------------------------------
    it('surfaces missing string handler methods with a TODO comment', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { items: [] }; },
            watch: {
                items: 'updateCount'
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain(
            "TODO: migrate watch entry manually: items: string handler 'updateCount' was not found in methods",
        );
        expect(result.script).not.toContain("import { watch } from 'vue';");
    });

    // -------------------------------------------------------------------------
    it('surfaces object-form watchers without a handler with a TODO comment', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                items: {
                    deep: true,
                }
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain('TODO: migrate watch entry manually: items: missing watcher handler');
    });

    // -------------------------------------------------------------------------
    it('surfaces undeclared watch targets with a TODO comment instead of generating undeclared refs', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                items(newItems) {
                    return newItems.length;
                }
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('watch: items: watch target is not declared in props, data, computed, or inject');
        expect(result.script).toContain(
            'TODO: migrate watch entry manually: items: watch target is not declared in props, data, computed, or inject',
        );
        expect(result.script).not.toContain('watch(() => items.value');
    });

    // -------------------------------------------------------------------------
    it('preserves async object-form inline watcher handlers', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { items: [], count: 0 }; },
            watch: {
                items: {
                    async handler(newItems) {
                        this.count = await Promise.resolve(newItems.length);
                    },
                    immediate: true,
                }
            },
        });`;
        const result = transformScript(js);

        expect(result.blockers).not.toContain('watch: items: unsupported watcher handler shape');
        expect(result.script).toContain('watch(() => items.value, async (newItems) => {');
        expect(result.script).toContain('count.value = await Promise.resolve(newItems.length);');
        expect(result.script).toContain('immediate: true');
    });

    // -------------------------------------------------------------------------
    it('surfaces non-literal deep/immediate watcher options for manual follow-up instead of erasing them', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            watch: {
                items: {
                    handler(newItems) {
                        return newItems;
                    },
                    deep: shouldTrackDeep,
                    immediate: getImmediate(),
                }
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('watch: items: deep must be a boolean literal');
        expect(result.blockers).toContain('watch: items: immediate must be a boolean literal');
        expect(result.script).toContain('TODO: migrate watch entry manually: items: deep must be a boolean literal');
        expect(result.script).toContain('TODO: migrate watch entry manually: items: immediate must be a boolean literal');
        expect(result.script).not.toContain('watch(() => items.value');
    });

    // -------------------------------------------------------------------------
    describe('route watcher source generation', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                watch: {
                    $route(to, from) {
                        this.handleRouteChange(to, from);
                    }
                },
                methods: {
                    handleRouteChange(to, from) {
                        return [to, from];
                    },
                },
            });`;
            result = transformScript(js);
        });

        it('uses a route snapshot getter as the watcher source so updates stay reactive and to/from remain distinct', () => {
            expect(result.script).toContain(
                'watch(() => ({ ...route, params: { ...route.params }, query: { ...route.query } }), (to, from) => {',
            );
            expect(result.script).not.toContain('$route.value');
        });

        it('imports and declares useRoute for the generated watcher', () => {
            expect(result.script).toMatch(/import\s*\{[^}]*useRoute[^}]*\}\s*from\s*['"]vue-router['"]/);
            expect(result.script).toContain('const route = useRoute();');
        });
    });

    // -------------------------------------------------------------------------
    describe('array-form props', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                props: ['label', 'value'],
                methods: {
                    getLabel() { return this.label; },
                },
            });`;
            result = transformScript(js);
        });

        it('rewrites this.label to props.label', () => {
            expect(result.script).toContain('props.label');
            expect(result.script).not.toContain('this.label');
        });
    });

    // -------------------------------------------------------------------------
    it('preserves both newVal and oldVal watcher parameters', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { count: 0 }; },
            watch: {
                count(newVal, oldVal) { console.log(newVal, oldVal); }
            },
        });`;
        const result = transformScript(js);
        expect(result.script).toContain('(newVal, oldVal) =>');
    });

    // -------------------------------------------------------------------------
    describe('inject object form', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                inject: { repositoryFactory: { from: 'repositoryFactory', default: null } },
                methods: {
                    create() { return this.repositoryFactory.create(); }
                },
            });`;
            result = transformScript(js);
        });

        it('generates inject() call for object-form inject key', () => {
            expect(result.script).toContain("inject('repositoryFactory', null)");
        });

        it('rewrites this.repositoryFactory in methods', () => {
            expect(result.script).not.toContain('this.repositoryFactory');
            expect(result.script).toContain('repositoryFactory.create()');
        });
    });

    // -------------------------------------------------------------------------
    describe('inject object form preserves aliases and defaults', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                inject: {
                    localFactory: 'repositoryFactory',
                    nullableService: { from: 'service', default: null },
                    filters: { from: 'filters', default: () => [] },
                },
                methods: {
                    getFactory() { return this.localFactory; },
                    getNullableService() { return this.nullableService; },
                    getFilters() { return this.filters; },
                },
            });`;
            result = transformScript(js);
        });

        it('uses the source inject key for aliased object-form inject entries', () => {
            expect(result.script).toContain("const localFactory = inject('repositoryFactory');");
        });

        it('preserves non-factory default values', () => {
            expect(result.script).toContain("const nullableService = inject('service', null);");
        });

        it('preserves factory defaults with treatDefaultAsFactory=true', () => {
            expect(result.script).toContain("const filters = inject('filters', () => [], true);");
        });

        it('still rewrites this.* references against the local injected names', () => {
            expect(result.script).toContain('return localFactory;');
            expect(result.script).toContain('return nullableService;');
            expect(result.script).toContain('return filters;');
            expect(result.script).not.toContain('this.localFactory');
            expect(result.script).not.toContain('this.nullableService');
            expect(result.script).not.toContain('this.filters');
        });
    });

    // -------------------------------------------------------------------------
    it('preserves inject object-form method shorthand defaults as factory inject defaults', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                filters: { from: 'filters', default() { return []; } },
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain("const filters = inject('filters', function() {");
        expect(result.script).toContain('return [];');
        expect(result.script).toContain('}, true);');
    });

    // -------------------------------------------------------------------------
    it('treats function-expression inject defaults as factory defaults', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                filters: { from: 'filters', default: function() { return []; } },
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain("const filters = inject('filters', function() { return []; }, true);");
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API when a method depends on an unsupported inject initializer', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                repositoryFactory: createRepositoryFactory,
            },
            methods: {
                create() { return this.repositoryFactory.create(); }
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: repositoryFactory: unsupported inject definition');
        expect(result.script).not.toContain('createExtendableSetup(');
        expect(result.script).toContain('create() { return this.repositoryFactory.create(); }');
        expect(result.script).not.toContain("const repositoryFactory = inject('repositoryFactory');");
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API when inject aliases are not valid identifiers', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                'repository-factory': 'repositoryFactory',
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: repository-factory is not a valid JavaScript identifier');
        expect(result.script).not.toContain('createExtendableSetup(');
        expect(result.script).not.toContain("const repository-factory = inject('repositoryFactory');");
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API for shorthand inject object entries', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                repositoryFactory,
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: repositoryFactory: shorthand inject entries must be migrated manually');
        expect(result.script).not.toContain('createExtendableSetup(');
        expect(result.script).not.toContain("const repositoryFactory = inject('repositoryFactory');");
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API for unsupported inject object members', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                ...sharedInject,
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: ...sharedInject: unsupported inject entry');
        expect(result.script).not.toContain('createExtendableSetup(');
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API for unsupported array-form inject entries', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: ['repositoryFactory', ...sharedInject],
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: ...sharedInject: unsupported inject entry');
        expect(result.script).not.toContain('createExtendableSetup(');
    });

    // -------------------------------------------------------------------------
    it('falls back to the Options API for unsupported inject root shapes', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: createInjectConfig(),
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.scriptType).toBe('options');
        expect(result.blockers).toContain('inject: inject must be an array or object literal');
        expect(result.script).not.toContain('createExtendableSetup(');
        expect(result.script).not.toContain('const createInjectConfig = inject(');
    });

    // -------------------------------------------------------------------------
    it('uses unref() for watch sources targeting injected dependencies emitted as plain constants', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: ['repositoryFactory'],
            watch: {
                repositoryFactory(newFactory) {
                    this.handleFactoryChange(newFactory);
                },
            },
            methods: {
                handleFactoryChange(newFactory) {
                    return newFactory;
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.script).toMatch(/import\s*\{[^}]*watch[^}]*unref[^}]*\}\s*from\s*'vue';/);
        expect(result.script).toContain('watch(() => unref(repositoryFactory), (newFactory) => {');
        expect(result.script).not.toContain('watch(() => repositoryFactory.value');
    });

    // -------------------------------------------------------------------------
    it('supports object-form watcher handlers declared as function expressions', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { externalCount: 0, count: 0 }; },
            watch: {
                externalCount: {
                    handler: function(newVal, oldVal) {
                        this.count = newVal + oldVal;
                    },
                    immediate: true,
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.blockers).not.toContain('watch: externalCount: unsupported watcher handler shape');
        expect(result.script).toContain('watch(() => externalCount.value, (newVal, oldVal) => {');
        expect(result.script).toContain('count.value = newVal + oldVal;');
        expect(result.script).toContain('immediate: true');
    });

    // -------------------------------------------------------------------------
    it('supports object-form watcher handlers declared as arrow functions', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { externalCount: 0, count: 0 }; },
            watch: {
                externalCount: {
                    handler: (newVal) => {
                        this.count = newVal;
                    },
                    deep: true,
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.blockers).not.toContain('watch: externalCount: unsupported watcher handler shape');
        expect(result.script).toContain('watch(() => externalCount.value, (newVal) => {');
        expect(result.script).toContain('count.value = newVal;');
        expect(result.script).toContain('deep: true');
    });

    // -------------------------------------------------------------------------
    it('emits defineEmits([]) when $emit is used with a dynamic event name', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            methods: {
                fire(eventName) { this.$emit(eventName); }
            },
        });`;
        const result = transformScript(js);
        expect(result.script).toContain('const emit = defineEmits([])');
    });

    // -------------------------------------------------------------------------
    it('preserves object-form emits validators', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            emits: {
                save(payload) {
                    return payload !== null;
                },
            },
            methods: {
                onSave(payload) { this.$emit('save', payload); }
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.script).toContain('const emit = defineEmits({');
        expect(result.script).toContain('save(payload)');
        expect(result.script).toContain('return payload !== null;');
        expect(result.script).not.toContain("const emit = defineEmits(['save']);");
    });

    // -------------------------------------------------------------------------
    it('replaces this.$store with a throwing IIFE, not a bare this.$store reference', () => {
        const js = `
        Shopware.Component.register('sw-store-user', {
            methods: {
                getCount() { return this.$store.getters['sw-example/count']; },
            },
        });
    `;
        const result = transformScript(js);
        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain(
            '$store usage requires manual migration to the appropriate Pinia store or composable',
        );
        expect(result.script).not.toContain('this.$store');
        expect(result.script).toContain('throw new Error');
        expect(result.script).toContain('TODO: migrate $store');
    });

    // -------------------------------------------------------------------------
    it('marks unsupported top-level Options API options as partially migratable', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            provide() { return { foo: this.foo }; },
            components: { 'sw-child': swChild },
            directives: { focus },
            beforeCreate() { this.bootstrap(); },
            methods: {
                bootstrap() {},
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain('provide option requires manual migration');
        expect(result.blockers).toContain('components option requires manual verification');
        expect(result.blockers).toContain('directives option requires manual migration');
        expect(result.blockers).toContain('beforeCreate hook requires manual migration');
        expect(result.script).toContain('TODO: migrate `provide` manually');
        expect(result.script).toContain('TODO: verify local component registrations in `components:`');
        expect(result.script).toContain('TODO: migrate `directives` manually');
        expect(result.script).toContain('TODO: `beforeCreate` was dropped');
    });

    // -------------------------------------------------------------------------
    it('marks unsupported computed spread entries as partially migratable', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            computed: {
                ...mapPropertyErrors('product', ['name']),
                title() {
                    return 'Title';
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migratable');
        expect(result.blockers).toContain("computed: ...mapPropertyErrors('product', ['name']): unsupported computed entry");
        expect(result.script).toContain(
            "TODO: migrate computed entry manually: computed: ...mapPropertyErrors('product', ['name']): unsupported computed entry",
        );
        expect(result.script).toContain('const title = computed(() => {');
    });

    // -------------------------------------------------------------------------
    it('migrates function-valued computed entries instead of dropping them', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() { return { title: 'Title' }; },
            computed: {
                label: function() {
                    return this.title;
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.script).toContain('const label = computed(() => {');
        expect(result.script).toContain('return title.value;');
    });

    // -------------------------------------------------------------------------
    it('migrates arrow-function computed entries instead of dropping them', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            computed: {
                label: () => 'Title',
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.script).toContain('const label = computed(() => {');
        expect(result.script).toContain("return 'Title';");
    });

    // -------------------------------------------------------------------------
    it('does not rewrite this references inside strings, comments, or static template text', () => {
        const js = [
            "Shopware.Component.register('sw-test', {",
            "    data() { return { title: 'Title' }; },",
            '    methods: {',
            "        literalRoute() { return 'this.$route'; },",
            '        staticTemplate(label) { return `debug: ${label} this.title`; },',
            '        commentedEmit() {',
            "            // this.$emit('save') must stay a comment",
            "            return 'done';",
            '        },',
            '        executableTemplate() { return `${this.title}`; },',
            '    },',
            '});',
        ].join('\n');
        const result = transformScript(js);

        expect(result.status).toBe('fully-migratable');
        expect(result.script).toContain("return 'this.$route';");
        expect(result.script).toContain('return `debug: ${label} this.title`;');
        expect(result.script).toContain("// this.$emit('save') must stay a comment");
        expect(result.script).toContain('return `${title.value}`;');
        expect(result.script).not.toContain('useRoute');
        expect(result.script).not.toContain('defineEmits');
    });

    // -------------------------------------------------------------------------
    describe('deferred identifier collision handling', () => {
        it('uses the first semantic fallback when a component public name takes the preferred router name', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { router: null };
                },
                methods: {
                    goBack() { this.$router.back(); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const $router = useRouter();');
            expect(result.script).toContain('$router.back();');
            expect(result.script).toContain('const router = ref(null);');
            expect(result.script).not.toContain('const router = useRouter();');
        });

        it('does not let a discouraged $router data member shadow the instance $router', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { router: null, $router: null };
                },
                methods: {
                    goBack() { this.$router.back(); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const vueRouter = useRouter();');
            expect(result.script).toContain('vueRouter.back();');
            expect(result.script).not.toContain('const router = useRouter();');
            expect(result.script).not.toContain('const $router = useRouter();');
        });

        it('falls back to preferred name numbering when all semantic names are taken', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { router: null, $router: null, vueRouter: null };
                },
                methods: {
                    goBack() { this.$router.back(); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const router2 = useRouter();');
            expect(result.script).toContain('router2.back();');
        });

        it('avoids silent local shadowing by considering method parameters during name selection', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                methods: {
                    getRouteName(route) {
                        return this.$route.name || route.name;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const $route = useRoute();');
            expect(result.script).toContain('return $route.name || route.name;');
            expect(result.script).not.toContain('const route = useRoute();');
        });

        it('uses fallback identifiers for all composables when preferred names are component public names', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return {
                        router: null,
                        route: null,
                        slots: null,
                        attrs: null,
                        t: null,
                    };
                },
                computed: {
                    routeName() { return this.$route.name; },
                    hasDefaultSlot() { return Boolean(this.$slots.default); },
                },
                methods: {
                    goBack() { this.$router.back(); },
                    getClass() { return this.$attrs.class; },
                    getLabel() { return this.$t('sw.test.label'); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const $router = useRouter();');
            expect(result.script).toContain('const $route = useRoute();');
            expect(result.script).toContain('const $slots = useSlots();');
            expect(result.script).toContain('const $attrs = useAttrs();');
            expect(result.script).toContain('const { t: $t } = useI18n();');
            expect(result.script).toContain('$router.back();');
            expect(result.script).toContain('return $route.name;');
            expect(result.script).toContain('return Boolean($slots.default);');
            expect(result.script).toContain('return $attrs.class;');
            expect(result.script).toContain("return $t('sw.test.label');");
            expect(result.script).toContain('const router = ref(null);');
            expect(result.script).toContain('const route = ref(null);');
            expect(result.script).toContain('const slots = ref(null);');
            expect(result.script).toContain('const attrs = ref(null);');
            expect(result.script).toContain('const t = ref(null);');
            expect(result.script).not.toContain('const router = useRouter();');
            expect(result.script).not.toContain('const route = useRoute();');
            expect(result.script).not.toContain('const slots = useSlots();');
            expect(result.script).not.toContain('const attrs = useAttrs();');
            expect(result.script).not.toContain('const { t } = useI18n();');
        });

        it('numbers the i18n identifier when t, $t, and translate are already taken', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { t: null, $t: null, translate: null };
                },
                methods: {
                    getLabel() { return this.$tc('sw.test.label', 2); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain('const { t: t2 } = useI18n();');
            expect(result.script).toContain("return t2('sw.test.label', 2);");
            expect(result.script).toContain('const t = ref(null);');
            expect(result.script).toContain('const $t = ref(null);');
            expect(result.script).toContain('const translate = ref(null);');
            expect(result.script).not.toContain('const { t } = useI18n();');
            expect(result.script).not.toContain('const { t: $t } = useI18n();');
            expect(result.script).not.toContain('const { t: translate } = useI18n();');
        });

        it('uses a fallback emit identifier when emit is a component public name', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { emit: null };
                },
                methods: {
                    save() { this.$emit('save'); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain("const $emit = defineEmits(['save']);");
            expect(result.script).toContain("$emit('save');");
            expect(result.script).toContain('const emit = ref(null);');
            expect(result.script).not.toContain('const emit = defineEmits(');
        });

        it('does not let a discouraged $emit data member shadow the instance $emit', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { emit: null, $emit: null };
                },
                methods: {
                    save() { this.$emit('save'); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migratable');
            expect(result.script).toContain("const vueEmit = defineEmits(['save']);");
            expect(result.script).toContain("vueEmit('save');");
            expect(result.script).toContain('const emit = ref(null);');
            expect(result.script).toContain('const $emit = ref(null);');
            expect(result.script).not.toContain('const emit = defineEmits(');
            expect(result.script).not.toContain('const $emit = defineEmits(');
        });
    });

    // -------------------------------------------------------------------------
    describe('block-component data scope handling', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('block-component.index.js'));
        });

        it('does not import reactive for data-scope generation', () => {
            expect(result.script).not.toMatch(/import\s*\{[^}]*reactive[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('does not emit a local $dataScope variable', () => {
            expect(result.script).not.toContain('const $dataScope =');
        });
    });
});
