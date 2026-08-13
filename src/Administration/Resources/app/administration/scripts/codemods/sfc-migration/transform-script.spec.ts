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
    describe('provide-component: converts provide() into provide(key, value) calls', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('provide-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('provides the migrated method and the migrated data value', () => {
            expect(result.script).toContain("provide('registerCardItem', registerCardItem);");
            expect(result.script).toContain("provide('card-scope', scopeName.value);");
        });

        it('does not contain any this. references', () => {
            expect(result.script).not.toMatch(/\bthis\./);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('expose-component: converts the expose option into defineExpose', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('expose-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        // Script setup is closed by default, so the explicit list is what keeps
        // the public surface the Options API `expose` declared.
        it('lists the exposed members in defineExpose', () => {
            expect(result.script).toContain(
                [
                    'defineExpose({',
                    '    focus,',
                    '    isOpen,',
                    '});',
                ].join('\n'),
            );
        });

        it('emits defineExpose after the swDefinePublic marker', () => {
            expect(result.script).toMatch(/swDefinePublic\(\{[\s\S]*\}\);\n\ndefineExpose\(\{/);
        });

        // The override API and the template-ref API are different surfaces: a
        // member can be public without being exposed and the other way round.
        it('keeps every migrated member in the public override API', () => {
            expect(result.script).toContain(
                [
                    'swDefinePublic({',
                    '    isOpen,',
                    '    stateLabel,',
                    '    focus,',
                    '});',
                ].join('\n'),
            );
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('route-guard-component: registers the in-component guards with their composables', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('route-guard-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('imports the guard composables from vue-router', () => {
            expect(result.script).toContain("import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router';");
        });

        it('keeps the guard signature and rewrites the body', () => {
            expect(result.script).toContain('onBeforeRouteLeave((to, from, next) => {');
            expect(result.script).toContain('if (isDirty.value && !confirmLeave()) {');
        });

        it('keeps an async guard async', () => {
            expect(result.script).toContain('onBeforeRouteUpdate(async (to, from, next) => {');
            expect(result.script).toContain('await nextTick();');
        });

        // The guards register on the route record; they are not members a plugin
        // overrides.
        it('keeps the guards out of the public override API', () => {
            expect(result.publicNames).toEqual([
                'isDirty',
                'confirmLeave',
            ]);
        });

        // The composables run during setup, so the slot only has to be past the
        // bindings a guard body reads.
        it('emits the guards after the lifecycle hooks and before swDefinePublic', () => {
            expect(result.script).toMatch(/onBeforeRouteUpdate\([\s\S]*\n\nswDefinePublic\(\{/u);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('route guards the codemod cannot register', () => {
        it('keeps the TODO for beforeRouteEnter, which has no composable', () => {
            const js = `Shopware.Component.register('sw-test', {
                beforeRouteEnter(to, from, next) { next(); },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toEqual([
                'beforeRouteEnter: option is not supported by the SFC migration and requires manual migration',
            ]);
            expect(result.script).not.toContain('onBeforeRouteEnter');
        });

        it('drops a guard whose body still depends on the instance', () => {
            const js = `Shopware.Component.register('sw-test', {
                beforeRouteLeave(to, from, next) {
                    next(this.unknownHelper());
                },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toEqual([
                "beforeRouteLeave: route guard uses unknown this property 'unknownHelper'",
            ]);
            expect(result.script).not.toContain('onBeforeRouteLeave');
        });

        // A function value's `this` is not the receiver the rewrite assumes, the
        // same reason lifecycle hooks only migrate in method form.
        it('drops a guard that is not defined as a method', () => {
            const js = `Shopware.Component.register('sw-test', {
                beforeRouteUpdate: function (to, from, next) { next(); },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toEqual(['beforeRouteUpdate: route guard must be defined as a method to be migrated']);
            expect(result.script).not.toContain('onBeforeRouteUpdate');
        });
    });

    // -------------------------------------------------------------------------
    describe('methods with an identifier value', () => {
        // `{ getKey: get }` resolves `get` in module scope, never on the
        // instance, and the generated block inherits that binding unchanged.
        it.each([
            [
                'a destructured module-level const',
                'const { get } = Shopware.Utils;',
                'getKey: get',
                'const getKey = get;',
            ],
            [
                'a default import',
                "import externalHelper from './helper';",
                'toLabel: externalHelper',
                'const toLabel = externalHelper;',
            ],
            [
                'a named import',
                "import { formatKey } from './helper';",
                'getKey: formatKey',
                'const getKey = formatKey;',
            ],
        ])('re-declares a method value that is %s', (_case, moduleSource, methodSource, expected) => {
            const js = `${moduleSource}

            Shopware.Component.register('sw-test', {
                methods: { ${methodSource} },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain(expected);
            expect(result.publicNames).toContain(expected.split(' ')[1]);
        });

        // Nothing in the generated block declares the name, so re-declaring it
        // would emit a reference to something that does not exist.
        it('keeps the fallback for an identifier the module never binds', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: { getKey: someGlobalHelper },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain('methods: getKey: method value must be an inline function');
            expect(result.script).not.toContain('const getKey =');
        });

        it('keeps the fallback for a value that is not an identifier at all', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: { getKey: 'not a function' },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain('methods: getKey: method value must be an inline function');
        });
    });

    // -------------------------------------------------------------------------
    describe('device-component: binds $device once from the setup instance', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('device-component.index.js'));
        });

        // The DeviceHelper singleton is closed over inside the plugin's
        // install(), so there is nothing to import — but reading it once during
        // setup is equivalent, which is why this is not a TODO.
        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
            expect(result.script).not.toContain('TODO');
        });

        it('captures the device helper in the composable slot', () => {
            expect(result.script).toContain('const device = getCurrentInstance()?.proxy?.$device;');
            expect(result.script).toContain("import { ref, computed, getCurrentInstance, onMounted } from 'vue';");
        });

        it('rewrites every this.$device access to the captured binding', () => {
            expect(result.script).toContain('return device.getSystemKey();');
            expect(result.script).toContain('isCompact.value = device.getViewportWidth() < 500;');
            expect(result.script).not.toContain('this.$device');
        });

        // The captured binding is not part of the migrated Options API surface.
        it('keeps the device binding out of the public override API', () => {
            expect(result.publicNames).toEqual([
                'isCompact',
                'systemKey',
            ]);
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('$device usage the codemod cannot bind', () => {
        // `onResize({ component: this })` hands the helper the instance itself,
        // which has no setup equivalent — so the method stays a manual follow-up
        // for the bare `this`, not for `$device`.
        it('still falls back when the method also passes a bare this', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    check() { return true; },
                    register() {
                        this.$device.onResize({ listener: this.check, component: this });
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toEqual(['methods: register uses bare this']);
            expect(result.script).not.toContain('onResize');
        });

        it('renames the captured binding when the component declares device itself', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { device: 'desktop' }; },
                methods: {
                    check() { return this.$device.getViewportWidth() > 500; },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('const $device = getCurrentInstance()?.proxy?.$device;');
            expect(result.script).toContain('return $device.getViewportWidth() > 500;');
        });
    });

    // -------------------------------------------------------------------------
    describe('computed-spread-component: expands the statically analysable computed spreads', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('computed-spread-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        // The names are the ones map-errors.service.ts derives, so an override
        // or a template written against the Options API keeps working.
        it('names the error entries like the service does', () => {
            expect(result.script).toContain('const productNameError = computed(() => {');
            expect(result.script).toContain('const productStockError = computed(() => {');
            expect(result.script).toContain('const lineItemsQuantityError = computed(() => {');
        });

        it('ports the entity error getter, reading the entity through the rewritten member', () => {
            expect(result.script).toContain('const entity = product.value;');
            expect(result.script).toContain("return Shopware.Store.get('error').getApiError(entity, 'name');");
        });

        it('ports the collection error getter', () => {
            expect(result.script).toContain('const entityCollection = lineItems.value;');
            expect(result.script).toContain('if (!Array.isArray(entityCollection)) { return null; }');
        });

        it('reads the mapped store key off the store expression the spread named', () => {
            expect(result.script).toContain("return Store.get('swProductDetail').loading;");
        });

        // The entries have no counterpart in the source file, so the reader is
        // told where each one came from.
        it('marks every generated entry with the spread it came from', () => {
            expect(result.script).toContain("// from the ...mapPropertyErrors('product', …) computed spread");
            expect(result.script).toContain("// from the ...mapCollectionPropertyErrors('lineItems', …) computed spread");
            expect(result.script).toContain(
                "// from the ...mapState(() => Store.get('swProductDetail'), …) computed spread",
            );
        });

        it('lists the expanded entries in the public override API', () => {
            expect(result.publicNames).toEqual(
                expect.arrayContaining([
                    'productNameError',
                    'productStockError',
                    'lineItemsQuantityError',
                    'loading',
                ]),
            );
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('computed spread shapes', () => {
        function transformComputedSpread(spreadSource: string): ReturnType<typeof transformScript> {
            return transformScript(`Shopware.Component.register('sw-test', {
                data() { return { product: null }; },
                computed: {
                    ${spreadSource},
                },
            });`);
        }

        it.each([
            [
                'a store id string literal',
                "...mapState('swProductDetail', ['loading'])",
                "return Shopware.Store.get('swProductDetail').loading;",
            ],
            [
                'a store composable identifier',
                "...mapState(useProductDetailStore, ['loading'])",
                'return useProductDetailStore().loading;',
            ],
            [
                'an expression-bodied arrow',
                "...mapState(() => Shopware.Store.get('swProductDetail'), ['loading'])",
                "return Shopware.Store.get('swProductDetail').loading;",
            ],
        ])('resolves the mapState store from %s', (_case, spreadSource, expectedBody) => {
            const result = transformComputedSpread(spreadSource);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain(expectedBody);
        });

        // Every shape below hides its keys or its store behind runtime state, so
        // the entry keeps the manual TODO it had before spreads were expanded.
        it.each([
            [
                'mapPageErrors, whose argument is a cross-module config object',
                "...mapPageErrors({ 'sw-product.detail': { product: ['name'] } })",
            ],
            [
                'a mapState object form, which renames the keys',
                "...mapState(() => Shopware.Store.get('swProductDetail'), { isLoading: 'loading' })",
            ],
            [
                'a block-bodied mapState arrow, which can run statements first',
                "...mapState(() => { return Shopware.Store.get('swProductDetail'); }, ['loading'])",
            ],
            [
                'a non-literal property list',
                '...mapPropertyErrors(entityName, propertyNames)',
            ],
            [
                'an unknown helper',
                "...mapSomethingElse('product', ['name'])",
            ],
            [
                'a helper reached through a member expression',
                "...helpers.mapPropertyErrors('product', ['name'])",
            ],
        ])('keeps the manual TODO for %s', (_case, spreadSource) => {
            const result = transformComputedSpread(spreadSource);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers.join('\n')).toContain('unsupported computed entry');
        });

        // The helper itself returns an empty object for a missing list, so the
        // spread contributes nothing — that is an expansion, not a failure.
        it('expands a property-error helper without properties into no entry at all', () => {
            const result = transformComputedSpread("...mapPropertyErrors('product')");

            expect(result.status).toBe('fully-migrated');
            expect(result.script).not.toContain('computed(');
        });
    });

    // -------------------------------------------------------------------------
    describe('global-alias-component: expands module-local aliases of globals in props and emits', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('global-alias-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        // defineProps is hoisted above the module-level code the codemod copies
        // in, so the alias itself cannot be read there — but the global path it
        // stands for can.
        it('writes the global path instead of the destructured alias in defineProps', () => {
            expect(result.script).toContain('type: Shopware.Data.Criteria,');
            expect(result.script).toContain('default: Shopware.Context.app.adminEsEnable ?? false,');
        });

        it('resolves an alias of an alias to the full path in defineEmits', () => {
            expect(result.script).toContain('save: (payload) => Shopware.Utils.types.isObject(payload),');
        });

        // The aliases stay declared for the rest of the setup body; only the
        // hoisted macros must not depend on them.
        it('keeps the module-level alias declarations', () => {
            expect(result.script).toContain('const { Criteria } = Shopware.Data;');
        });

        it('matches the complete converted script snapshot', () => {
            expect(result.script).toMatchSnapshot();
        });
    });

    // -------------------------------------------------------------------------
    describe('watch-path-component: converts dotted watch keys into optional-chained getters', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('watch-path-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        // Vue's path getter stops walking as soon as an intermediate value is
        // missing, which is what the optional chaining reproduces.
        it('resolves the root segment like a plain watch target and chains the rest optionally', () => {
            expect(result.script).toContain('watch(() => props.item?.price?.net, (value) => {');
            expect(result.script).toContain('watch(() => entity.value?.customFields,');
        });

        it('keeps the handler name, deep, and immediate options of a dotted watcher', () => {
            expect(result.script).toContain(
                'watch(() => entity.value?.name, (...args) => applyLabel(...args), { immediate: true });',
            );
            expect(result.script).toContain('}, { deep: true });');
        });

        // The snapshot getter only exists so that watching the route object
        // itself triggers; a path watcher reads a value that changes on its own.
        it('watches a $route path through the route object without the snapshot getter', () => {
            expect(result.script).toContain('watch(() => route?.name, () => {');
            expect(result.script).toContain('const route = useRoute();');
            expect(result.script).not.toContain('...route, params:');
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
    it('does not re-import a vue binding the module already imports under the same name', () => {
        const js = `import { computed, ref as vueRef } from 'vue';
        import template from './sw-test.html.twig';

        const fallbackTitle = vueRef('Fallback');

        Shopware.Component.register('sw-test', {
            template,
            data() {
                return { title: 'Title' };
            },
            computed: {
                upperTitle() { return this.title.toUpperCase(); },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migrated');
        expect(result.script).toContain(`import { computed, ref as vueRef } from 'vue';`);
        // `computed` resolves to the module's own import; `ref` is still
        // generated, because the module only bound it under an alias.
        expect(result.script).toContain(`import { ref } from 'vue';`);
        expect(result.script).toContain('const upperTitle = computed(');
    });

    // -------------------------------------------------------------------------
    it('emits no vue import at all when the module already imports every needed name', () => {
        const js = `import { ref } from 'vue';
        import template from './sw-test.html.twig';

        Shopware.Component.register('sw-test', {
            template,
            data() {
                return { title: 'Title' };
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migrated');
        expect(result.script.match(/from 'vue';/gu)).toHaveLength(1);
        expect(result.script).toContain("const title = ref('Title');");
    });

    // -------------------------------------------------------------------------
    it('rewrites this.$te to the te member of the i18n composer', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            computed: {
                label() {
                    return this.$te('sw.test.label') ? this.$t('sw.test.label') : 'sw.test.label';
                },
            },
            methods: {
                hasFallback(key, locale) { return this.$te(key, locale); },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migrated');
        expect(result.blockers).toEqual([]);
        expect(result.script).toContain(`import { useI18n } from 'vue-i18n';`);
        expect(result.script).toContain('const { t, te } = useI18n();');
        expect(result.script).toContain("return te('sw.test.label') ? t('sw.test.label') : 'sw.test.label';");
        // The legacy $te(key, locale?) signature is the composer's te signature.
        expect(result.script).toContain('return te(key, locale);');
        expect(result.script).not.toMatch(/\bthis\.\$te\b/);
    });

    // -------------------------------------------------------------------------
    it('imports useI18n for a component that only checks whether a snippet exists', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            methods: {
                hasLabel(key) { return this.$te(key); },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('fully-migrated');
        expect(result.script).toContain('const { te } = useI18n();');
        // `t` is not destructured when nothing translates.
        expect(result.script).not.toMatch(/const \{ t[,}]/);
    });

    // -------------------------------------------------------------------------
    describe('instance-api-component: keeps $el as a placeholder when no element can host a ref', () => {
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

        it('names no root element ref for the caller to write into the template', () => {
            expect(result.rootElementRefName).toBeNull();
        });
    });

    // -------------------------------------------------------------------------
    describe('root-el-component: replaces $el with a generated root template ref', () => {
        let result: ReturnType<typeof transformScript>;

        beforeAll(() => {
            result = transformScript(readFixture('root-el-component.index.js'), { canHostRootElementRef: true });
        });

        // A real ref is equivalent to what `$el` resolved to, so nothing about
        // this component needs a manual follow-up any more.
        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
            expect(result.script).not.toContain('TODO');
        });

        it('declares the ref and reports its name back to the caller', () => {
            expect(result.rootElementRefName).toBe('rootEl');
            expect(result.script).toContain('const rootEl = ref(null);');
        });

        it('rewrites every $el usage — hooks, comparisons, and DOM calls alike', () => {
            expect(result.script).toContain('rootEl.value.addEventListener(');
            expect(result.script).toContain('rootEl.value.removeEventListener(');
            expect(result.script).toContain('if (event.target !== rootEl.value) {');
            expect(result.script).toContain('rootEl.value.scrollIntoView(');
        });

        it('needs no instance handle any more', () => {
            expect(result.script).not.toContain('getCurrentInstance');
        });

        // The ref is a private setup binding, not part of the Options API surface.
        it('keeps the root ref out of the public override API', () => {
            expect(result.publicNames).not.toContain('rootEl');
        });

        it('renames the ref when the component already declares rootEl', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { rootEl: null }; },
                methods: {
                    focus() { this.$el.focus(); },
                },
            });`;
            const renamed = transformScript(js, { canHostRootElementRef: true });

            expect(renamed.rootElementRefName).toBe('$el');
            expect(renamed.script).toContain('const $el = ref(null);');
            expect(renamed.script).toContain('$el.value.focus();');
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
    it('surfaces a watch path whose root is not a declared member with a TODO comment', () => {
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
            "TODO: migrate watch entry manually: items.length: watch path root 'items' is not declared in props, data, computed, or inject",
        );
        expect(result.script).not.toContain('watch(() => items');
    });

    // -------------------------------------------------------------------------
    it('surfaces a watch path with a segment that is not an identifier with a TODO comment', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            data() {
                return { items: [] };
            },
            watch: {
                'items[0].label': 'updateCount'
            },
            methods: {
                updateCount() {},
            },
        });`;
        const result = transformScript(js);

        expect(result.script).toContain(
            'TODO: migrate watch entry manually: items[0].label: watch path segments must be valid identifiers to be migrated',
        );
        expect(result.script).not.toContain('watch(() => items');
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
        expect(result.blockers).toContain("provide: foo value uses unknown this property 'foo'");
        expect(result.blockers).toContain('components option requires manual verification');
        expect(result.blockers).toContain('directives option requires manual migration');
        expect(result.blockers).toContain('beforeCreate hook requires manual migration');
        expect(result.script).toContain('TODO: migrate provide manually');
        expect(result.script).toContain('TODO: verify local component registrations in `components:`');
        expect(result.script).toContain('TODO: migrate `directives` manually');
        expect(result.script).toContain('TODO: `beforeCreate` was dropped');
    });

    // -------------------------------------------------------------------------
    describe('provide option', () => {
        const providingMethodComponent = `Shopware.Component.register('sw-test', {
            provide() {
                return {
                    registerSidebarItem: this.registerSidebarItem,
                };
            },
            data() {
                return { items: [] };
            },
            methods: {
                registerSidebarItem(item) {
                    this.items.push(item);
                },
            },
        });`;

        it('migrates a provide() method into provide(key, value) calls', () => {
            const result = transformScript(providingMethodComponent);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain("provide('registerSidebarItem', registerSidebarItem);");
            expect(result.script).toMatch(/import\s*\{[^}]*\bprovide\b[^}]*\}\s*from\s*'vue'/);
            expect(result.script).not.toContain('TODO: migrate `provide`');
        });

        it('migrates a provide object literal with identifier and string-literal keys', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide: {
                    'card-scope': 'sw-card',
                    cardLimit: 42,
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain("provide('card-scope', 'sw-card');");
            expect(result.script).toContain("provide('cardLimit', 42);");
        });

        it('keeps provided keys out of the public override API', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide: {
                    cardScope: 'sw-card',
                },
            });`;
            const result = transformScript(js);

            expect(result.publicNames).toEqual([]);
            expect(result.script).toContain('swDefinePublic({});');
        });

        it('emits the provide calls after the watchers and before the created body', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide() {
                    return { registerItem: this.registerItem };
                },
                data() {
                    return { count: 0 };
                },
                watch: {
                    count() {
                        this.registerItem(this.count);
                    },
                },
                created() {
                    this.registerItem(1);
                },
                methods: {
                    registerItem(item) {
                        this.count = item;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            // Options API order: the watch options are applied before provide, and
            // created runs after it. The methods are `const` declarations, so the
            // slot also keeps the provided value out of their temporal dead zone.
            // One regex so a missing marker fails instead of passing via indexOf(-1).
            expect(result.script).toMatch(
                /const registerItem =[\s\S]*watch\(\(\) =>[\s\S]*provide\('registerItem'[\s\S]*registerItem\(1\);/,
            );
        });

        it('migrates a function-expression provide, which does receive the instance', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide: function () {
                    return { cardScope: this.scopeName };
                },
                data() {
                    return { scopeName: 'sw-card' };
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain("provide('cardScope', scopeName.value);");
        });

        it('escapes a provide key that cannot be written into a plain string literal', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide: {
                    'line\\nbreak': 'sw-card',
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain("provide('line\\u000abreak', 'sw-card');");
        });

        it('falls back to the manual TODO for computed provide keys', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide() {
                    return { [dynamicKey]: 'sw-card' };
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain("provide: [dynamicKey]: 'sw-card': unsupported provide entry");
            expect(result.script).toContain(
                "// TODO: migrate provide manually: provide: [dynamicKey]: 'sw-card': unsupported provide entry",
            );
            expect(result.script).not.toContain("provide('");
        });

        it.each([
            [
                'shorthand',
                'provide() { return { cardScope }; }',
                'provide: cardScope: unsupported provide entry',
            ],
            [
                'spread',
                'provide() { return { ...defaults }; }',
                'provide: ...defaults: unsupported provide entry',
            ],
        ])('falls back to the manual TODO for %s provide entries', (_name, provideOption, blocker) => {
            const result = transformScript(`Shopware.Component.register('sw-test', { ${provideOption} });`);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain(blocker);
            expect(result.script).not.toContain("provide('");
        });

        it('falls back to the manual TODO when a provided value uses unresolved this access', () => {
            const js = `Shopware.Component.register('sw-test', {
                provide() {
                    return {
                        notify: this.createNotificationSuccess,
                        cardScope: 'sw-card',
                    };
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain(
                "provide: notify value uses unknown this property 'createNotificationSuccess'",
            );
            // A partially migrated provide would change what descendants receive.
            expect(result.script).not.toContain("provide('cardScope'");
        });

        it.each([
            [
                'an arrow value never receives the instance',
                "provide: () => ({ cardScope: 'sw-card' })",
            ],
            [
                'an async provide() resolves to a promise',
                "async provide() { return { cardScope: 'sw-card' }; }",
            ],
            [
                'a generator provide() provides its own keys',
                "*provide() { return { cardScope: 'sw-card' }; }",
            ],
            [
                'statements before the return can compute the keys',
                "provide() { const scope = 'sw-card'; return { cardScope: scope }; }",
            ],
        ])('falls back to the manual TODO because %s', (_name, provideOption) => {
            const result = transformScript(`Shopware.Component.register('sw-test', { ${provideOption} });`);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain(
                'provide: only a plain object or a non-arrow method returning an object literal can be mapped to provide(key, value) calls',
            );
            expect(result.script).not.toContain("provide('cardScope'");
        });
    });

    // -------------------------------------------------------------------------
    it('marks unsupported computed spread entries as partially migratable', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            computed: {
                ...mapPageErrors({ 'sw-product.detail': { product: ['name'] } }),
                title() {
                    return 'Title';
                },
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migrated');
        expect(result.blockers).toContain(
            "computed: ...mapPageErrors({ 'sw-product.detail': { product: ['name'] } }): unsupported computed entry",
        );
        expect(result.script).toContain(
            "TODO: migrate computed entry manually: computed: ...mapPageErrors({ 'sw-product.detail': { product: ['name'] } }): unsupported computed entry",
        );
        expect(result.script).toContain('const title = computed(() => {');
    });

    // -------------------------------------------------------------------------
    // An expanded getter reads `this.<entity>`, so an entity the component does
    // not declare drops the entry with the reason instead of expanding into a
    // getter that reads nothing.
    it('drops an expanded error entry whose entity is not a migrated member', () => {
        const js = `Shopware.Component.register('sw-test', {
            template,
            computed: {
                ...mapPropertyErrors('product', ['name']),
            },
        });`;
        const result = transformScript(js);

        expect(result.status).toBe('partially-migrated');
        expect(result.blockers).toContain("computed: productNameError uses unknown this property 'product'");
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

        it('destructures t and te from one useI18n() call with independent aliases', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { te: null };
                },
                methods: {
                    getLabel(key) { return this.$te(key) ? this.$t(key) : key; },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('const { t, te: $te } = useI18n();');
            expect(result.script).toContain('return $te(key) ? t(key) : key;');
            expect(result.script).toContain('const te = ref(null);');
        });

        it('uses a fallback translation-exists identifier when te and $te are already taken', () => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                data() {
                    return { te: null, $te: null };
                },
                methods: {
                    hasLabel(key) { return this.$te(key); },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('const { te: translationExists } = useI18n();');
            expect(result.script).toContain('return translationExists(key);');
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

        // The rewrite turns `this.action` into a bare `action`, which resolves in
        // the scope of the access — so the parameter would swallow the assignment.
        it('drops a member whose rewrite target is shadowed by its own parameter', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { action: null }; },
                methods: {
                    runAction(action) {
                        this.action = action;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, "methods: runAction uses rewrite target 'action' is shadowed by a local binding");
            expect(result.script).not.toContain('action.value = action');
        });

        it('drops a member whose rewrite target is shadowed by a const in a nested function', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { total: 0 }; },
                methods: {
                    recalculate(items) {
                        items.forEach(() => {
                            const total = 1;

                            this.total = total;
                        });
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, "methods: recalculate uses rewrite target 'total' is shadowed by a local binding");
        });

        // `var` hoists to the enclosing function, so the loop variable is still
        // in scope after the loop — where `this.action` would be rewritten to it.
        it('drops a member whose rewrite target is shadowed by a for-initializer var', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { action: 1 }; },
                methods: {
                    run() {
                        for (var action = 0; action < 3; action++) {}

                        this.action = 5;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, "methods: run uses rewrite target 'action' is shadowed by a local binding");
            expect(result.script).not.toContain('action.value = 5');
        });

        it('drops a member whose rewrite target is shadowed by a var in the function body', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { action: 1 }; },
                methods: {
                    run() {
                        if (Date.now()) {
                            var action = 0;
                        }

                        this.action = action;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, "methods: run uses rewrite target 'action' is shadowed by a local binding");
        });

        // `let` in a for-initializer is scoped to the loop, so an access after it
        // reads the setup binding and the member still migrates.
        it('keeps a member whose for-initializer let leaves the access alone', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { action: 1 }; },
                methods: {
                    run() {
                        for (let action = 0; action < 3; action++) {}

                        this.action = 5;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('action.value = 5;');
        });

        // A binding only shadows the accesses inside its own scope, so a member
        // that never reads through it still migrates.
        it('keeps a member whose same-named local sits in a sibling scope', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() { return { total: 0 }; },
                methods: {
                    recalculate(items) {
                        items.forEach(() => {
                            const total = 1;

                            window.console.log(total);
                        });

                        this.total = 1;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('total.value = 1;');
        });

        // `this.<prop>` is rewritten through the `props` object, so a local named
        // `props` captures the access just like a member-named local does.
        it('drops a member whose props access is shadowed by a local named props', () => {
            const js = `Shopware.Component.register('sw-test', {
                props: { label: { type: String, required: true } },
                methods: {
                    format(props) {
                        return \`\${props.prefix}\${this.label}\`;
                    },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, "methods: format uses rewrite target 'props' is shadowed by a local binding");
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

        // Emitting both `import { ref } from 'vue'` and `const ref = ref('x')`
        // was rejected by the build as a parse error long after the codemod
        // reported the component as migrated.
        it.each([
            [
                'ref',
                "data() { return { ref: 'x' }; }",
                "data: ref collides with the generated 'vue' import of the same name",
            ],
            [
                'computed',
                'computed: { computed() { return 1; } }',
                "computed: computed collides with the generated 'vue' import of the same name",
            ],
            [
                'watch',
                'methods: { watch() {} }',
                "methods: watch collides with the generated 'vue' import of the same name",
            ],
            [
                'onMounted',
                'methods: { onMounted() {} }',
                "methods: onMounted collides with the generated 'vue' import of the same name",
            ],
            [
                'unref',
                'methods: { unref() {} }',
                "methods: unref collides with the generated 'vue' import of the same name",
            ],
            [
                'nextTick',
                'methods: { nextTick() {} }',
                "methods: nextTick collides with the generated 'vue' import of the same name",
            ],
            [
                'provide',
                "inject: ['provide']",
                "inject: provide collides with the generated 'vue' import of the same name",
            ],
            [
                'useRouter',
                'methods: { useRouter() {} }',
                "methods: useRouter collides with the generated 'vue-router' import of the same name",
            ],
            [
                'useI18n',
                'methods: { useI18n() {} }',
                "methods: useI18n collides with the generated 'vue-i18n' import of the same name",
            ],
        ])('drops the member named %s instead of declaring a generated import twice', (name, optionSource, reason) => {
            const js = `Shopware.Component.register('sw-test', {
                template,
                ${optionSource},
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain(reason);
            expect(result.script).not.toMatch(new RegExp(`^const ${name}\\b`, 'mu'));
        });

        // `expose: []` closes the instance in the Options API, and a script setup
        // component is closed already, so there is nothing to emit or review.
        it('drops an empty expose array silently instead of emitting defineExpose', () => {
            const js = `Shopware.Component.register('sw-test', {
                expose: [],
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
            expect(result.script).not.toContain('defineExpose');
        });

        // A name repeated in the Options API list is a no-op there, but the same
        // key twice in the generated object literal is a lint error.
        it('emits a repeated expose entry once', () => {
            const js = `Shopware.Component.register('sw-test', {
                expose: ['focus', 'focus'],
                methods: {
                    focus() { return true; },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain(
                [
                    'defineExpose({',
                    '    focus,',
                    '});',
                ].join('\n'),
            );
        });

        it.each([
            [
                'a name that was never declared',
                "expose: ['focus']",
                "expose: 'focus' is not a migrated data, computed, method, or inject member",
            ],
            [
                'a name that was dropped during the migration',
                "expose: ['reload']",
                "expose: 'reload' is not a migrated data, computed, method, or inject member",
            ],
            [
                'a computed list',
                'expose: buildExposeList()',
                'expose: only an array of string literals can be mapped to defineExpose({ … })',
            ],
            [
                'an entry that is not a string literal',
                "expose: ['focus', methodName]",
                'expose: methodName: unsupported expose entry',
            ],
        ])('falls back to a manual TODO for expose with %s', (_case, exposeSource, expectedReason) => {
            const js = `Shopware.Component.register('sw-test', {
                ${exposeSource},
                data() { return { title: 'Title' }; },
                methods: {
                    reload() { return this.unknownHelper(); },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, expectedReason);
            // The reason text names the macro, so only a real call is rejected.
            expect(result.script).not.toMatch(/^defineExpose\(/m);
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

        it('omits a name option that only repeats the registered name', () => {
            const js = `Shopware.Component.register('sw-test', {
                name: 'sw-test',
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).not.toContain('defineOptions');
        });

        it('keeps a name option that differs from the registered name', () => {
            const js = `Shopware.Component.register('sw-test', {
                name: 'sw-other-name',
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain(`defineOptions({ name: 'sw-other-name' });`);
        });

        it('keeps emitting inheritAttrs when the redundant name option is dropped', () => {
            const js = `Shopware.Component.register('sw-test', {
                name: 'sw-test',
                inheritAttrs: false,
                data() { return { title: 'Title' }; },
            });`;
            const result = transformScript(js);

            expect(result.script).toContain('defineOptions({ inheritAttrs: false });');
            expect(result.script).not.toContain("name: 'sw-test'");
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

        it('reports a hook calling a dropped method as a dropped member, not as an unknown property', () => {
            const js = `Shopware.Component.register('sw-test', {
                mounted() {
                    this.mountedComponent();
                },
                methods: {
                    mountedComponent() {
                        return this.unknownApi;
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toContain("mounted: lifecycle hook uses dropped member 'mountedComponent'");
            expect(result.blockers).toContain("methods: mountedComponent uses unknown this property 'unknownApi'");
        });

        it('reports a computed property reading a dropped data entry as a dropped member', () => {
            const js = `Shopware.Component.register('sw-test', {
                data() {
                    return { label: this.unknownApi };
                },
                computed: {
                    upperLabel() {
                        return this.label.toUpperCase();
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toContain("computed: upperLabel uses dropped member 'label'");
            expect(result.blockers).toContain("data: label initializer uses unknown this property 'unknownApi'");
        });

        it('keeps the unknown-property message for a name the component never declared', () => {
            const js = `Shopware.Component.register('sw-test', {
                methods: {
                    notify() {
                        this.createNotificationSuccess({ message: 'Saved' });
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.blockers).toContain("methods: notify uses unknown this property 'createNotificationSuccess'");
            expect(result.blockers.join('\n')).not.toContain('dropped member');
        });

        it('marks props that reference a module-local declaration of its own as unsupported', () => {
            const js = `const propConfig = { type: String, required: true };

            Shopware.Component.register('sw-test', {
                props: {
                    label: propConfig,
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('defineProps({');
        });

        it('marks emits that reference a module-local declaration of its own as unsupported', () => {
            const js = `function onSave(payload) { return Boolean(payload); }

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

        // `let` can be reassigned between the declaration and the read, so the
        // path it held at declaration time is not what the macro would see.
        it('marks props referencing a let alias of a global as unsupported', () => {
            const js = `let Criteria = Shopware.Data.Criteria;

            Shopware.Component.register('sw-test', {
                props: {
                    criteria: { type: Criteria, required: false, default: null },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
            expect(result.script).not.toContain('Shopware.Data.Criteria,');
        });

        // A shorthand entry cannot carry a path: `{ Shopware.Data.Criteria }` is
        // not valid syntax, so the definition keeps the backoff.
        it('marks a shorthand global alias entry in props as unsupported', () => {
            const js = `const { Criteria } = Shopware.Data;

            Shopware.Component.register('sw-test', {
                props: {
                    criteria: { type: Object, required: false, default: () => ({ Criteria }) },
                },
            });`;
            const result = transformScript(js);

            expectManualFallback(result, 'props');
        });

        // A local of the same name is not a reference to the module-level alias,
        // so it neither blocks the migration nor gets rewritten.
        it('leaves a global alias name that a props default shadows untouched', () => {
            const js = `const { Criteria } = Shopware.Data;

            Shopware.Component.register('sw-test', {
                props: {
                    criteria: {
                        type: Object,
                        required: false,
                        default() {
                            const Criteria = { limit: 1 };

                            return Criteria;
                        },
                    },
                },
            });`;
            const result = transformScript(js);

            expect(result.status).toBe('fully-migrated');
            expect(result.script).toContain('return Criteria;');
            expect(result.script).not.toContain('return Shopware.Data.Criteria;');
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
