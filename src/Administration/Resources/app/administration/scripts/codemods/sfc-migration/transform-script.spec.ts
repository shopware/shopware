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

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });


        it('emits no extension wrapper — the native setup transform generates it', () => {
            expect(result.script).not.toContain('createExtendableSetup');
            expect(result.script).not.toContain('composition-extension-system');
        });

        it('imports the required Vue composables from vue', () => {
            expect(result.script).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
            expect(result.script).toContain('computed');
            expect(result.script).toContain('inject');
        });

        it('does not repeat the component name in the script — it comes from the .vue filename', () => {
            expect(result.script).not.toContain("'sw-simple-card'");
        });

        it('declares inject, data, computed, and method state as top-level setup code', () => {
            const markerStart = result.script.indexOf('swDefinePublic({');
            expect(markerStart).toBeGreaterThan(-1);
            expect(result.script).toContain("const repositoryFactory = inject('repositoryFactory');");
            expect(result.script).toContain("const title = ref('Default Title');");
            expect(result.script).toContain('const isLoading = ref(false);');
            expect(result.script.indexOf('const description = computed(')).toBeLessThan(markerStart);
        });

        it('declares the migrated state as the public override API', () => {
            expect(result.script).toContain(
                [
                    'swDefinePublic({',
                    '    repositoryFactory,',
                    '    title,',
                    '    isLoading,',
                    '    description,',
                    '    onSave,',
                    '});',
                ].join('\n'),
            );
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

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
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

        it('reports status fully-migrated', () => {
            expect(result.status).toBe('fully-migrated');
        });

        it('emits defineProps and defineEmits', () => {
            expect(result.script).toContain('const props = defineProps(');
            expect(result.script).toContain('const emit = defineEmits([');
            expect(result.script).toContain("'ready'");
        });

        it('places the created() body in the setup body (before onMounted), giving it access to inject values', () => {
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

        // The setup body is module-level code, where a bare `return` is a syntax
        // error — unlike the Options API method the body came from.
        it('wraps a created() body with a guard clause in an IIFE', () => {
            const withGuardClause = transformScript(`Shopware.Component.register('sw-guarded', {
                data() {
                    return { items: [] };
                },

                created() {
                    if (this.items.length === 0) {
                        return;
                    }

                    this.items = [];
                },
            });`);

            expect(withGuardClause.status).toBe('fully-migrated');
            expect(withGuardClause.script).toContain('(() => {');
            expect(withGuardClause.script).toContain('})();');
        });

        it('does not wrap a created() body whose only return is inside a callback', () => {
            const withCallbackReturn = transformScript(`Shopware.Component.register('sw-unguarded', {
                data() {
                    return { items: [] };
                },

                created() {
                    this.items = [
                        1,
                        2,
                    ].map((entry) => {
                        return entry * 2;
                    });
                },
            });`);

            expect(withCallbackReturn.status).toBe('fully-migrated');
            expect(withCallbackReturn.script).not.toContain('})();');
        });
    });

    // -------------------------------------------------------------------------
    describe('async-lifecycle-component: preserves async lifecycle hook bodies', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('async-lifecycle-component.index.js'));
        });

        it('reports status fully-migrated', () => {
            expect(result.status).toBe('fully-migrated');
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

        it('reports status fully-migrated', () => {
            expect(result.status).toBe('fully-migrated');
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
    describe('mixin-component: detects mixins as a blocker', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('mixin-component.index.js'));
        });

        // A mixin keeps part of the component in another file, so there is no
        // native setup component to emit — only the blocker.
        it('reports status not-migratable with mixins as the blocker', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('mixins');
            expect(result.script).toBe('');
            expect(result.publicNames).toEqual([]);
        });

        it('still reports the component name for the migration report', () => {
            expect(result.componentName).toBe('sw-mixin-list');
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
    describe('composables-component: rewrites $router, $route, $slots, $nextTick, $t, and $tc to their Composition API equivalents', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('composables-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
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

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('instance-api-component: keeps $el as a placeholder and requires manual follow-up', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('instance-api-component.index.js'));
        });

        it('reports status partially-migrated with a $el blocker', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.blockers.join('\n')).toContain('$el');
        });

        it('rewrites this.$el → getCurrentInstance()?.proxy?.$el with a TODO comment', () => {
            expect(result.script).toContain('/* TODO: $el */ getCurrentInstance()?.proxy?.$el');
            expect(result.script).not.toMatch(/\bthis\.\$el\b/);
            expect(result.script).toMatch(/import\s*\{[^}]*getCurrentInstance[^}]*\}\s*from\s*['"]vue['"]/);
        });
    });

    // -------------------------------------------------------------------------
    describe('inherit-attrs-component: emits defineOptions({ inheritAttrs: false }) and excludes inheritAttrs from the options object', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('inherit-attrs-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });


        it('emits defineOptions({ inheritAttrs: false }) at the top of the script', () => {
            expect(result.script).toContain('defineOptions({ inheritAttrs: false })');
        });

        it('declares inheritAttrs only through defineOptions, not as leftover setup state', () => {
            expect((result.script.match(/inheritAttrs/g) ?? []).length).toBe(1);
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

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('emits the debounced method as a const assignment preserving the debounce wrapper', () => {
            expect(result.script).toContain('const searchDebounce = debounce(');
        });

        it('rewrites this.doSearch() inside the debounce callback', () => {
            expect(result.script).toContain('doSearch()');
            expect(result.script).not.toMatch(/\bthis\.doSearch\b/);
        });

        it('includes searchDebounce in the swDefinePublic marker', () => {
            const markerStart = result.script.indexOf('swDefinePublic({');
            expect(markerStart).toBeGreaterThan(-1);
            expect(result.script.slice(markerStart)).toContain('searchDebounce');
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
    describe('extend-component: Shopware.Component.extend() is a blocker', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('extend-component.index.js'));
        });

        // The parent's options live in another file, so the child cannot be
        // converted until they are inlined by hand.
        it('reports status not-migratable with nothing emitted', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.script).toBe('');
        });

        it('lists extends with parent component name as a blocker', () => {
            expect(result.blockers).toContain('extends (parent: sw-button)');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
        expect(result.blockers).toContain('methods: ...sharedMethods: spread method entries must be migrated manually');
        expect(result.blockers).toContain('methods: shorthandMethod: shorthand method entries must be migrated manually');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('fully-migrated');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
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
    it('reports a blocker when a method depends on an unsupported inject initializer', () => {
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

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: repositoryFactory: unsupported inject definition');
    });

    // -------------------------------------------------------------------------
    it('reports a blocker when inject aliases are not valid identifiers', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                'repository-factory': 'repositoryFactory',
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: repository-factory is not a valid JavaScript identifier');
    });

    // -------------------------------------------------------------------------
    it('reports a blocker for shorthand inject object entries', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                repositoryFactory,
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: repositoryFactory: shorthand inject entries must be migrated manually');
    });

    // -------------------------------------------------------------------------
    it('reports a blocker for unsupported inject object members', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: {
                ...sharedInject,
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: ...sharedInject: unsupported inject entry');
    });

    // -------------------------------------------------------------------------
    it('reports a blocker for unsupported array-form inject entries', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: ['repositoryFactory', ...sharedInject],
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: ...sharedInject: unsupported inject entry');
    });

    // -------------------------------------------------------------------------
    it('reports a blocker for unsupported inject root shapes', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            inject: createInjectConfig(),
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('not-migratable');
        expect(result.script).toBe('');
        expect(result.blockers).toContain('inject: inject must be an array or object literal');
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

        expect(result.status).toBe('fully-migrated');
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

        expect(result.status).toBe('fully-migrated');
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
        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('partially-migrated');
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

        expect(result.status).toBe('fully-migrated');
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

        expect(result.status).toBe('fully-migrated');
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

        expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

            expect(result.status).toBe('fully-migrated');
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

    // -------------------------------------------------------------------------
    describe('unsupported-shape regression coverage: never silently generate non-equivalent setup code', () => {
        function expectManualFallback(result: ReturnType<typeof transformScript>, blocker: string): void {
            expect(result.status).not.toBe('fully-migrated');
            expect(result.blockers.join('\n')).toContain(blocker);
        }

        it('marks this[elementAccess] as unsupported instead of leaving executable this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { foo: 1, bar: 2 }; },
                methods: {
                    update(key, value) {
                        this[key] = value;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'dynamic this access');
            expect(result.script).not.toContain('this[key]');
        });

        it('marks unknown this properties as unsupported instead of leaving executable this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    notify() {
                        this.createNotificationSuccess({ message: 'Saved' });
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'unknown this property');
            expect(result.script).not.toContain('this.createNotificationSuccess');
        });

        it('marks bare this usage as unsupported instead of leaving setup with the wrong this binding', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    register() {
                        shortcutService.startEventListener({
                            scope: this,
                        });
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'bare this');
            expect(result.script).not.toContain('scope: this');
        });

        it('marks aliased instance access as unsupported instead of leaving a setup-time this alias', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { title: 'Title' }; },
                methods: {
                    getTitle() {
                        const vm = this;

                        return vm.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'this alias');
            expect(result.script).not.toContain('const vm = this;');
        });

        it('marks instance destructuring as unsupported instead of destructuring the setup this value', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { title: 'Title' }; },
                methods: {
                    getTitle() {
                        const { title } = this;

                        return title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'bare this');
            expect(result.script).not.toContain('const { title } = this;');
        });

        it('marks instance binding helpers as unsupported instead of binding callbacks to setup this', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    save() {},
                    scheduleSave() {
                        return this.save.bind(this);
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'bare this');
            expect(result.script).not.toContain('save.bind(this)');
        });

        it('marks unsupported data() return shapes as unsupported instead of dropping state', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return buildInitialState();
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'data');
        });

        it('marks shorthand data declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                data,
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'data');
            expect(result.script).not.toContain('this.title');
        });

        it('marks non-function data declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                data: buildInitialState,
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'data');
            expect(result.script).not.toContain('this.title');
        });

        it('marks data initializers that call component methods as unsupported instead of emitting a setup TDZ access', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return {
                        title: this.buildTitle(),
                    };
                },
                methods: {
                    buildTitle() {
                        return 'Title';
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'data');
            expect(result.script).not.toContain('const title = ref(buildTitle());');
        });

        it('marks nested data() returns as unsupported instead of using the wrong return object', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    function buildState() {
                        return { nestedOnly: true };
                    }

                    return buildState();
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'data');
            expect(result.script).not.toContain('const nestedOnly = ref(true);');
        });

        it('marks shorthand props declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                props,
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('this.title');
        });

        it('marks non-string array props entries as unsupported instead of filtering them out', () => {
            const js = `Shopware.Component.register('sw-test', {
                props: ['title', ...sharedProps],
                methods: {
                    getShared() {
                        return this.shared;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
        });

        it('marks object props with spread entries as unsupported instead of ignoring the spread', () => {
            const js = `Shopware.Component.register('sw-test', {
                props: {
                    title: String,
                    ...sharedProps,
                },
                methods: {
                    getShared() {
                        return this.shared;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
        });

        it('marks computed prop names as unsupported instead of dropping the dynamic prop from name extraction', () => {
            const js = `Shopware.Component.register('sw-test', {
                props: {
                    [dynamicPropName]: String,
                },
                methods: {
                    getDynamicValue() {
                        return this[dynamicPropName];
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('[dynamicPropName]: String');
        });

        it('marks non-literal props initializers as unsupported when prop names cannot be extracted', () => {
            const js = `Shopware.Component.register('sw-test', {
                props: buildProps(),
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('this.title');
        });

        it('marks props that reference local module declarations as unsupported for script setup macros', () => {
            const js = `const TYPES = [String];

            Shopware.Component.register('sw-test', {
                props: {
                    title: {
                        type: TYPES[0],
                    },
                },
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('defineProps({');
        });

        it('marks shorthand emits declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                emits,
                methods: {
                    save() {
                        this.$emit('save');
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
        });

        it('marks non-string array emits entries as unsupported instead of filtering them out', () => {
            const js = `Shopware.Component.register('sw-test', {
                emits: ['save', ...sharedEmits],
                methods: {
                    save() {
                        this.$emit('save');
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
        });

        it('marks computed emits names as unsupported instead of passing dynamic event validators through', () => {
            const js = `Shopware.Component.register('sw-test', {
                emits: {
                    [dynamicEventName](payload) {
                        return Boolean(payload);
                    },
                },
                methods: {
                    save(payload) {
                        this.$emit(dynamicEventName, payload);
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
            expect(result.script).not.toContain('[dynamicEventName]');
        });

        it('marks unsupported emits initializers as unsupported instead of replacing them with inferred emits', () => {
            const js = `Shopware.Component.register('sw-test', {
                emits: buildEmits(),
                methods: {
                    save() {
                        this.$emit('save');
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
        });

        it('marks object emits with spread entries as unsupported instead of passing spread to defineEmits', () => {
            const js = `Shopware.Component.register('sw-test', {
                emits: {
                    ...sharedEmits,
                    save(payload) {
                        return Boolean(payload);
                    },
                },
                methods: {
                    save(payload) {
                        this.$emit('save', payload);
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
            expect(result.script).not.toContain('...sharedEmits');
        });

        it('marks emits that reference local module declarations as unsupported for script setup macros', () => {
            const js = `const isValidSave = (payload) => Boolean(payload);

            Shopware.Component.register('sw-test', {
                emits: {
                    save: isValidSave,
                },
                methods: {
                    save(payload) {
                        this.$emit('save', payload);
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
            expect(result.script).not.toContain('defineEmits({');
        });

        it('marks shorthand methods declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods,
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'methods');
        });

        it('marks non-object methods declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: buildMethods(),
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'methods');
        });

        it('marks spread methods entries as unsupported instead of dropping the spread methods', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    ...sharedMethods,
                    save() {},
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'methods');
        });

        it('marks methods assigned to external references as unsupported instead of assuming instance binding is preserved', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    save: externalSave,
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'methods');
            expect(result.script).not.toContain('const save = externalSave;');
        });

        it('marks bare this inside raw method expressions as unsupported instead of preserving nested instance binding', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    run: debounce(function() {
                        nested(function() {
                            return this;
                        });
                    }),
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'bare this');
            expect(result.script).not.toContain('return this;');
        });

        it('marks shorthand computed declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                computed,
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'computed');
            expect(result.script).not.toContain('this.title');
        });

        it('marks function-valued lifecycle hooks as unsupported instead of dropping them', () => {
            const js = `Shopware.Component.register('sw-test', {
                created: function() {
                    this.bootstrap();
                },
                methods: {
                    bootstrap() {},
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'created');
        });

        it('marks shorthand lifecycle hook declarations as unsupported instead of dropping them', () => {
            const js = `Shopware.Component.register('sw-test', {
                created,
                methods: {
                    bootstrap() {},
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'created');
        });

        it('marks shorthand watch declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { title: 'Title' }; },
                watch,
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'watch');
        });

        it('marks shorthand inject declarations as unsupported instead of treating them as absent', () => {
            const js = `Shopware.Component.register('sw-test', {
                inject,
                methods: {
                    getRepository() {
                        return this.repositoryFactory;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'inject');
            expect(result.script).not.toContain('this.repositoryFactory');
        });

        it('marks destructured watcher parameters as unsupported instead of corrupting the parameter list', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { item: null }; },
                watch: {
                    item({ id }) {
                        this.useId(id);
                    },
                },
                methods: {
                    useId(id) {
                        return id;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'watch');
            expect(result.script).not.toContain('(id) =>');
        });

        it('marks watcher parameters with defaults or rest syntax as unsupported instead of dropping parameter syntax', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { item: null }; },
                watch: {
                    item(newValue = {}) {
                        this.useValue(newValue);
                    },
                },
                methods: {
                    useValue(value) {
                        return value;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'watch');
            expect(result.script).not.toContain('(newValue) =>');
        });

        it('marks computed setters with default parameters as unsupported instead of dropping parameter syntax', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { item: null }; },
                computed: {
                    itemProxy: {
                        get() {
                            return this.item;
                        },
                        set(value = null) {
                            this.item = value;
                        },
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'computed');
            expect(result.script).not.toContain('set: (value) =>');
        });

        it('marks dynamic inheritAttrs as unsupported instead of assuming true', () => {
            const js = `Shopware.Component.register('sw-test', {
                inheritAttrs: shouldInheritAttrs(),
                methods: {
                    noop() {},
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'inheritAttrs');
        });

        it('marks root-level component option spreads as unsupported instead of ignoring hidden options', () => {
            const js = `Shopware.Component.register('sw-test', {
                ...buildComponentOptions(),
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'component option spread');
        });

        it('marks dynamic root component option names as unsupported instead of ignoring hidden options', () => {
            const js = `Shopware.Component.register('sw-test', {
                [dynamicOptionName]: buildRuntimeOption(),
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'dynamic option');
        });

        it('marks computed render declarations as not migratable instead of missing the render blocker', () => {
            const js = `Shopware.Component.register('sw-test', {
                ['render']() {
                    return h('div');
                },
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('not-migratable');
            expect(result.blockers.join('\n')).toContain('render');
            expect(result.script).toBe('');
        });

        it('marks non-literal component names as unsupported instead of renaming to unknown-component', () => {
            const js = `Shopware.Component.register(componentName, {
                data() { return { title: 'Title' }; },
                methods: {
                    getTitle() {
                        return this.title;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'component name');
            expect(result.script).not.toContain("name: 'unknown-component'");
        });

        it('marks dynamic component option names as unsupported instead of emitting invalid defineOptions', () => {
            const js = `Shopware.Component.register('sw-test', {
                name: componentDisplayName,
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'name');
            expect(result.script).not.toContain('defineOptions({ name: componentDisplayName })');
        });

        it('marks duplicate public setup names as unsupported instead of emitting duplicate declarations', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return {
                        save: false,
                    };
                },
                methods: {
                    save() {
                        return this.save;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'duplicate');
            expect(result.script).not.toContain('const save = ref(false);');
            expect(result.script).not.toContain('const save = () =>');
        });

        it.each([
            [
                'beforeRouteEnter',
                'beforeRouteEnter(to, from, next) { next(); }',
            ],
            [
                'beforeRouteLeave',
                'beforeRouteLeave(to, from, next) { next(); }',
            ],
            [
                'beforeRouteUpdate',
                'beforeRouteUpdate(to, from, next) { next(); }',
            ],
            [
                'metaInfo',
                "metaInfo() { return { title: 'Title' }; }",
            ],
            [
                'shortcuts',
                "shortcuts: { 'SYSTEMKEY+S': 'save' }",
            ],
            [
                'errorCaptured',
                'errorCaptured() { return false; }',
            ],
            [
                'expose',
                "expose: ['focus']",
            ],
            [
                'extensionApiDevtoolInformation',
                "extensionApiDevtoolInformation: { property: 'value' }",
            ],
            [
                'saveFinish',
                'saveFinish() { return true; }',
            ],
        ])('marks unsupported top-level option %s as requiring manual migration', (optionName, optionSource) => {
            const js = `Shopware.Component.register('sw-test', {
                ${optionSource},
                methods: {
                    save() {},
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, optionName);
        });

        it.each([
            [
                '$el',
                'return this.$el;',
            ],
            [
                '$parent',
                'return this.$parent;',
            ],
            [
                '$root',
                'return this.$root;',
            ],
            [
                '$options',
                'return this.$options;',
            ],
            [
                '$forceUpdate',
                'this.$forceUpdate();',
            ],
        ])('marks placeholder rewrite for %s as requiring manual follow-up', (apiName, statement) => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    useApi() {
                        ${statement}
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, apiName);
        });

        it('drops a method that calls another method dropped for unresolved this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    dropped() {
                        return this.unknownApi;
                    },
                    callDropped() {
                        return this.dropped();
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'unknown this property');
            expect(result.script).not.toContain('this.dropped');
            expect(result.script).not.toContain('this.unknownApi');
        });

        it('drops a method that references a name removed as a duplicate public binding', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return { collision: 1 };
                },
                methods: {
                    collision() {
                        return 2;
                    },
                    useCollision() {
                        return this.collision;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'duplicate');
            expect(result.script).not.toContain('this.collision');
        });

        it('drops a computed property that calls a method dropped for unresolved this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                computed: {
                    value() {
                        return this.dropped();
                    },
                },
                methods: {
                    dropped() {
                        return this.unknownApi;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'computed');
            expect(result.script).not.toContain('this.dropped');
            expect(result.script).not.toContain('this.unknownApi');
        });

        it('drops a computed property that references a name removed as a duplicate public binding', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return { collision: 1 };
                },
                computed: {
                    collision() {
                        return 2;
                    },
                    useCollision() {
                        return this.collision;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'duplicate');
            expect(result.script).not.toContain('this.collision');
        });

        it('drops a watcher that calls a method dropped for unresolved this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return { count: 0 };
                },
                watch: {
                    count() {
                        this.dropped();
                    },
                },
                methods: {
                    dropped() {
                        return this.unknownApi;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'watch');
            expect(result.script).not.toContain('this.dropped');
            expect(result.script).not.toContain('this.unknownApi');
        });

        it('drops a lifecycle hook that calls a method dropped for unresolved this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                mounted() {
                    this.dropped();
                },
                methods: {
                    dropped() {
                        return this.unknownApi;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'mounted');
            expect(result.script).not.toContain('this.dropped');
            expect(result.script).not.toContain('this.unknownApi');
        });

        it('marks props that reference a destructured module-local declaration as unsupported', () => {
            const js = `const { propConfig } = Shopware.Utils;

            Shopware.Component.register('sw-test', {
                props: {
                    label: propConfig,
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('defineProps({');
        });

        it('marks emits that reference a destructured module-local declaration as unsupported', () => {
            const js = `const { onSave } = Shopware.Utils;

            Shopware.Component.register('sw-test', {
                emits: {
                    save: onSave,
                },
                methods: {
                    save(payload) {
                        this.$emit('save', payload);
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'emits');
            expect(result.script).not.toContain('defineEmits({');
        });

        it('does not back off when an emits validator parameter matches an unrelated module-local name', () => {
            const js = `const payload = 'module local';

            Shopware.Component.register('sw-test', {
                emits: {
                    save(payload) {
                        return Boolean(payload);
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('does not back off when a prop key matches an unrelated module-local name', () => {
            const js = `const label = 'module local';

            Shopware.Component.register('sw-test', {
                props: {
                    label: String,
                },
                methods: {
                    getLabel() {
                        return this.label;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });
    });
});
