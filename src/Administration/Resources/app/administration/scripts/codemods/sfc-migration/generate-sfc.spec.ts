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
            result = mergeComponentFiles(readFixture('block-component.html.twig'), readFixture('block-component.index.js'));
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

        it('passes the global $dataScope to <sw-block> without generating a local data scope', () => {
            expect(result.sfc).toContain('<sw-block name="sw_block_card" :data="$dataScope">');
            expect(result.sfc).not.toContain('const $dataScope =');
            expect(result.sfc).not.toMatch(/import\s*\{[^}]*reactive[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('does not define $dataScope for components without twig blocks', () => {
            const simple = mergeComponentFiles(
                readFixture('simple-component.html.twig'),
                readFixture('simple-component.index.js'),
            );
            expect(simple.sfc).not.toContain('$dataScope');
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('mixin-component: partially migrated SFC — template converted, script kept as Options API without createExtendableSetup', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles('<div class="sw-mixin-list"></div>', readFixture('mixin-component.index.js'));
        });

        it('reports status partially-migrated with the unresolved mixin listed as a blocker', () => {
            expect(result.status).toBe('partially-migrated');
            // `listing` has no composable in the registry, so the component keeps the backoff.
            expect(result.blockers.some((blocker) => blocker.startsWith('mixins'))).toBe(true);
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

    describe('listing-mixin-component: scaffolded listing page — useListing() owns the state and calls back into getList', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('listing-mixin-component.html.twig'),
                readFixture('listing-mixin-component.index.js'),
            );
        });

        it('reports status partially-migrated with the listing verification as its only blocker', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toEqual([
                expect.stringContaining("the 'listing' mixin was scaffolded"),
            ]);
        });

        it('produces a <script setup> block instead of the Options API backoff', () => {
            expect(result.sfc).toContain('<script setup>');
        });

        it('leads with a TODO naming the checks the codemod cannot make', () => {
            expect(result.sfc).toContain("// TODO: verify the 'listing' migration:");
        });

        it('hands the component getList and its own filters to useListing', () => {
            expect(result.sfc).toContain("import { useListing } from 'src/app/composables/use-listing';");
            expect(result.sfc).toContain('getList: (...args) => getList(...args),');
            expect(result.sfc).toContain('filters: () => filters.value,');
        });

        it('routes the listing fields the component set in data() into the useListing options', () => {
            expect(result.sfc).toContain("sortBy: 'name',");
            expect(result.sfc).toContain("searchConfigEntity: 'product',");
            expect(result.sfc).toContain("storeKey: 'grid.filter.product',");
            expect(result.sfc).toContain('filterCriteria: [],');
            // The composable owns that state, so no second ref is declared for it.
            expect(result.sfc).not.toContain("const sortBy = ref('name');");
        });

        it('destructures the listing state under the names the template already uses', () => {
            expect(result.sfc).toMatch(/const \{[^}]*\bpage\b[^}]*\blimit\b[^}]*\btotal\b[^}]*\} = useListing\(/);
        });

        it('rewrites the listing fields getList reads and writes to the composable refs', () => {
            expect(result.sfc).toContain('new Criteria(page.value, limit.value)');
            expect(result.sfc).toContain('total.value = items.total;');
            expect(result.sfc).not.toContain('this.total');
        });

        it('rewrites the component calling its own getList', () => {
            expect(result.sfc).toContain('getList();');
            expect(result.sfc).not.toContain('this.getList');
        });

        it('matches the complete scaffolded SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('manual-follow-up partials: generated setup scripts stay wrapped in <script setup>', () => {
        it('keeps <script setup> for partially-migratable setup output', () => {
            const result = mergeComponentFiles(
                '<div>{{ count }}</div>',
                `Shopware.Component.register('sw-partial-setup', {
                    template,
                    data() { return { count: 0 }; },
                    watch: {
                        'settings.count'(newVal) { this.count = newVal; },
                    },
                });`,
            );

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain('watch: settings.count: nested watch paths are not supported');
            expect(result.sfc).toContain('<script setup>');
            expect(result.sfc).not.toContain('<script>');
            expect(result.sfc).toContain(
                'TODO: migrate watch entry manually: settings.count: nested watch paths are not supported',
            );
        });
    });

    describe('template-only mixin member: the composable must still be imported and declared', () => {
        // A component that opts into the `placeholder` mixin but only calls
        // `placeholder(...)` in its template (never `this.placeholder` in the
        // script). The migrated <script setup> must still declare the binding,
        // otherwise the template references an undefined `placeholder`.
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                `<div class="sw-country-name">{{ placeholder(country, 'name', 'fallback') }}</div>`,
                `Shopware.Component.register('sw-country-name', {
                    template,
                    mixins: [
                        Shopware.Mixin.getByName('placeholder'),
                    ],
                    props: {
                        country: { type: Object, required: true },
                    },
                });`,
            );
        });

        it('imports usePlaceholder for the template-only usage', () => {
            expect(result.sfc).toContain(
                "import { usePlaceholder } from 'src/app/composables/use-placeholder';",
            );
        });

        it('declares the placeholder binding used by the template', () => {
            expect(result.sfc).toContain('const { placeholder } = usePlaceholder();');
        });
    });

    describe('mixin member override: backs off when the component redefines a mixin member', () => {
        // The component redefines `createNotification`, a member the notification
        // mixin provides. A component member wins under Vue override semantics, but
        // the composable calls its own copy internally, so migrating would silently
        // drop the override. The whole component keeps the Options-API backoff.
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                '<div class="sw-demo"></div>',
                `Shopware.Component.register('sw-demo', {
                    template,
                    mixins: [
                        Shopware.Mixin.getByName('notification'),
                    ],
                    methods: {
                        createNotification(config) {
                            return Shopware.Store.get('notification').createNotification({ ...config, growl: false });
                        },
                        onSave() {
                            this.createNotificationSuccess({ message: 'Saved' });
                        },
                    },
                });`,
            );
        });

        it('keeps the Options API backoff (plain <script>, not <script setup>)', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.sfc).not.toContain('<script setup>');
        });

        it('reports the redefined mixin member as the blocker', () => {
            expect(
                result.blockers.some((blocker) => blocker.includes("component redefines 'createNotification'")),
            ).toBe(true);
        });
    });

    describe('legacy i18n signature: backs off instead of renaming to an incompatible t() call', () => {
        it('backs off on $t(key, localeLiteral) — a string 2nd arg Composition t treats as a default message', () => {
            const result = mergeComponentFiles(
                '<div class="sw-demo"></div>',
                `Shopware.Component.register('sw-demo', {
                    template,
                    methods: {
                        label() { return this.$t('sw-demo.label', 'en-GB'); },
                    },
                });`,
            );

            expect(result.status).toBe('partially-migrated');
            expect(result.sfc).not.toContain('<script setup>');
            expect(result.blockers.some((b) => b.startsWith('i18n:'))).toBe(true);
        });

        it('backs off on $tc(key, choice, values) — the reordered legacy signature', () => {
            const result = mergeComponentFiles(
                '<div class="sw-demo"></div>',
                `Shopware.Component.register('sw-demo', {
                    template,
                    methods: {
                        count() { return this.$tc('sw-demo.count', 2, { name: 'x' }); },
                    },
                });`,
            );

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers.some((b) => b.startsWith('i18n:'))).toBe(true);
        });

        it('backs off on $t(key, `localeBacktick`) — a backtick locale is still a string 2nd arg', () => {
            const result = mergeComponentFiles(
                '<div class="sw-demo"></div>',
                'Shopware.Component.register(\'sw-demo\', {\n' +
                    '    template,\n' +
                    '    methods: {\n' +
                    '        label() { return this.$t(\'sw-demo.label\', `en-GB`); },\n' +
                    '    },\n' +
                    '});',
            );

            expect(result.status).toBe('partially-migrated');
            expect(result.sfc).not.toContain('<script setup>');
            expect(result.blockers.some((b) => b.startsWith('i18n:'))).toBe(true);
        });

        it('does not back off on the modern $t(key, { named }) signature', () => {
            const result = mergeComponentFiles(
                '<div class="sw-demo"></div>',
                `Shopware.Component.register('sw-demo', {
                    template,
                    methods: {
                        label() { return this.$t('sw-demo.label', { name: 'x' }); },
                    },
                });`,
            );

            expect(result.blockers.some((b) => b.startsWith('i18n:'))).toBe(false);
        });
    });

    describe('template binding collision: backs off when a template-read mixin member cannot keep its name', () => {
        // The template reads `placeholder(...)`, but the module already binds the
        // name `placeholder`, so the composable destructure would be renamed. The
        // codemod cannot rewrite the template, so it keeps the Options-API backoff.
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                `<div class="sw-demo">{{ placeholder(country, 'name', 'fallback') }}</div>`,
                `import placeholder from './external-placeholder';

                Shopware.Component.register('sw-demo', {
                    template,
                    mixins: [
                        Shopware.Mixin.getByName('placeholder'),
                    ],
                    props: {
                        country: { type: Object, required: true },
                    },
                });`,
            );
        });

        it('keeps the Options API backoff (plain <script>, not <script setup>)', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.sfc).not.toContain('<script setup>');
        });

        it('reports the colliding template binding as the blocker', () => {
            expect(result.blockers.some((b) => b.includes("template reads 'placeholder'"))).toBe(true);
        });
    });

    describe('instance-api-component: warnings field reports $el usage', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('instance-api-component.html.twig'),
                readFixture('instance-api-component.index.js'),
            );
        });

        it('reports status partially-migrated because $el has no setup equivalent', () => {
            expect(result.status).toBe('partially-migrated');
        });

        it('populates warnings with a $el message', () => {
            expect(result.warnings).toHaveLength(1);
            expect(result.warnings[0]).toContain('$el usage detected');
        });
    });

    describe('simple-component: warnings field is empty when $el is not used', () => {
        it('has no warnings', () => {
            const result = mergeComponentFiles(
                readFixture('simple-component.html.twig'),
                readFixture('simple-component.index.js'),
            );
            expect(result.warnings).toEqual([]);
        });
    });

    describe('render-component: not migratable — no SFC is produced', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles('', readFixture('render-component.index.js'));
        });

        it('reports status not-migratable with render function as the blocker', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('render function');
        });

        it('produces an empty SFC string — nothing is written to disk for this component', () => {
            expect(result.sfc).toBe('');
        });
    });

    describe('unsupported twig templates: not migratable — no SFC is produced', () => {
        it('reports twig extends as a blocker instead of throwing a generic error', () => {
            const result = mergeComponentFiles(
                "{% extends 'bar' %}{% block foo %}<div>content</div>{% endblock %}",
                readFixture('simple-component.index.js'),
            );

            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('twig extends');
            expect(result.sfc).toBe('');
        });

        it('reports twig block syntax inside comments as a blocker', () => {
            const result = mergeComponentFiles(
                '{# {% block hidden %}<div>commented</div>{% endblock %} #}<div>content</div>',
                readFixture('simple-component.index.js'),
            );

            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('twig syntax inside comment');
            expect(result.sfc).toBe('');
        });

        it('reports orphaned cross-block v-else cases as a blocker', () => {
            const result = mergeComponentFiles(
                `
{% block sw_first %}
    <div v-else>fallback</div>
{% endblock %}
                `,
                readFixture('simple-component.index.js'),
            );

            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('orphaned cross-block v-else');
            expect(result.sfc).toBe('');
        });
    });
});
