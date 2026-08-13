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
 * Fully-migrated components are native `<script setup>` components that declare
 * their public override API with swDefinePublic(). The build-time transform in
 * build/vue-setup-transform lowers that into the extension runtime, so no
 * wrapper is written into the SFC itself.
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

        it('emits no extension wrapper — the native setup transform generates it', () => {
            expect(result.sfc).not.toContain('createExtendableSetup');
            expect(result.sfc).not.toContain('composition-extension-system');
        });

        it('imports the required Vue composables from vue', () => {
            expect(result.sfc).toMatch(/import\s*\{[^}]*ref[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('declares inject, data, computed, and method state before the swDefinePublic marker', () => {
            const markerStart = result.sfc.indexOf('swDefinePublic({');
            expect(markerStart).toBeGreaterThan(-1);
            expect(result.sfc.indexOf("inject('repositoryFactory')")).toBeLessThan(markerStart);
            expect(result.sfc.indexOf("ref('Default Title')")).toBeLessThan(markerStart);
            expect(result.sfc.indexOf('ref(false)')).toBeLessThan(markerStart);
            expect(result.sfc.indexOf('computed(')).toBeLessThan(markerStart);
        });

        it('declares the migrated state as the public override API', () => {
            expect(result.sfc).toContain(
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

        it('places <template> before <script setup> in the file', () => {
            expect(result.sfc.indexOf('<template>')).toBeLessThan(result.sfc.indexOf('<script setup>'));
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('block-component: fully migrated SFC with twig blocks replaced and native setup script', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(readFixture('block-component.html.twig'), readFixture('block-component.index.js'));
        });

        it('reports status fully-migrated with no blockers', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
        });

        it('replaces all twig block syntax with <sw-block> components in the <template> section', () => {
            expect(result.sfc).toContain('<sw-block name="sw_block_card">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_header">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_content">');
            expect(result.sfc).toContain('<sw-block name="sw_block_card_footer">');
            expect(result.sfc).toContain('<sw-block-parent/>');
            expect(result.sfc).not.toContain('{%');
            expect(result.sfc).not.toContain('%}');
        });

        it('emits no extension wrapper — the native setup transform generates it', () => {
            expect(result.sfc).not.toContain('createExtendableSetup');
        });

        it('declares inject, all data refs, computed properties, watch, method, and lifecycle hook before the marker', () => {
            const markerStart = result.sfc.indexOf('swDefinePublic({');
            expect(markerStart).toBeGreaterThan(-1);
            expect(result.sfc.indexOf("inject('acl')")).toBeLessThan(markerStart);
            expect(result.sfc.indexOf("ref('Block Card')")).toBeLessThan(markerStart);
            expect(result.sfc.indexOf('computed(')).toBeLessThan(markerStart);
            expect(result.sfc.indexOf('watch(')).toBeLessThan(markerStart);
            expect(result.sfc.indexOf('onMounted(')).toBeLessThan(markerStart);
        });

        it('declares the migrated state as the public override API', () => {
            expect(result.sfc).toContain('swDefinePublic({');
            expect(result.sfc).toContain('    canEdit,');
            expect(result.sfc).toContain('    onAction,');
        });

        it('leaves the <sw-block> data binding to the transform, which owns it', () => {
            expect(result.sfc).not.toContain('$dataScope');
            expect(result.sfc).not.toMatch(/import\s*\{[^}]*reactive[^}]*\}\s*from\s*['"]vue['"]/);
        });

        it('matches the complete SFC output snapshot', () => {
            expect(result.sfc).toMatchSnapshot();
        });
    });

    describe('mixin-component: Options API backoff — not migratable as a native setup SFC', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles('<div class="sw-mixin-list"></div>', readFixture('mixin-component.index.js'));
        });

        it('reports status not-migratable with mixins listed as a blocker', () => {
            expect(result.status).toBe('not-migratable');
            expect(result.blockers).toContain('mixins');
        });

        // Every .vue file must be a native setup component, so an Options API
        // script has no valid SFC to be written into.
        it('produces an empty SFC string — nothing is written to disk', () => {
            expect(result.sfc).toBe('');
        });

        it('reports the component name so the blocker can be attributed', () => {
            expect(result.componentName).toBe('sw-mixin-list');
        });
    });

    describe('transform rejections: output the build would refuse is reported, not written', () => {
        it('reports a reserved top-level binding name instead of writing the SFC', () => {
            const result = mergeComponentFiles(
                '<div>{{ Shopware }}</div>',
                `Shopware.Component.register('sw-reserved-binding', {
                    data() {
                        return { Shopware: 'shadowed' };
                    },
                });`,
            );

            expect(result.status).toBe('not-migratable');
            expect(result.blockers.join('\n')).toContain('native setup transform:');
            expect(result.blockers.join('\n')).toContain('reserved');
            expect(result.sfc).toBe('');
        });
    });

    describe('components without public state: the marker is still mandatory', () => {
        it('emits swDefinePublic({}) when nothing was migrated into public state', () => {
            const result = mergeComponentFiles(
                '<div class="sw-empty"></div>',
                `Shopware.Component.register('sw-empty', {
                    inheritAttrs: false,
                });`,
            );

            expect(result.status).toBe('fully-migrated');
            expect(result.sfc).toContain('swDefinePublic({});');
        });
    });

    describe('manual-follow-up partials: generated setup scripts stay wrapped in <script setup>', () => {
        it('keeps <script setup> for partially-migrated setup output', () => {
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
            expect(result.blockers).toContain(
                "watch: settings.count: watch path root 'settings' is not declared in props, data, computed, or inject",
            );
            expect(result.sfc).toContain('<script setup>');
            expect(result.sfc).not.toContain('<script>');
            expect(result.sfc).toContain(
                "TODO: migrate watch entry manually: settings.count: watch path root 'settings' is not declared in props, data, computed, or inject",
            );
        });
    });

    describe('generated import collisions: reported as a reason instead of a transform parse error', () => {
        // Emitting `import { ref } from 'vue'` next to `const ref = ref('x')`
        // used to reach the native setup transform, which rejected the whole
        // component with `Identifier 'ref' has already been declared` — a parse
        // error pointing at generated code instead of at the member to migrate.
        it('drops the colliding member and keeps the rest of the component migrated', () => {
            const result = mergeComponentFiles(
                '<div class="sw-collision">{{ title }}</div>',
                `Shopware.Component.register('sw-collision', {
                    data() {
                        return { ref: 'x', title: 'Title' };
                    },
                });`,
            );

            expect(result.status).toBe('partially-migrated');
            expect(result.blockers).toContain("data: ref collides with the generated 'vue' import of the same name");
            expect(result.blockers.join('\n')).not.toContain('native setup transform');
            // The import is still needed by the member that survived, which is
            // what made the collision fatal in the first place.
            expect(result.sfc).toContain("const title = ref('Title');");
            expect(result.sfc).not.toContain("const ref = ref('x');");
        });

        it('accepts a component whose module already imports the name the setup needs', () => {
            const result = mergeComponentFiles(
                '<div class="sw-preimported">{{ title }}</div>',
                `import { ref } from 'vue';

                Shopware.Component.register('sw-preimported', {
                    data() {
                        return { title: 'Title' };
                    },
                });`,
            );

            expect(result.status).toBe('fully-migrated');
            expect(result.blockers).toEqual([]);
            expect(result.sfc.match(/from 'vue';/gu)).toHaveLength(1);
        });
    });

    describe('root-el-component: $el becomes a ref on the first element inside the root block', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('root-el-component.html.twig'),
                readFixture('root-el-component.index.js'),
            );
        });

        // The two transforms have to agree on the name: the template writes the
        // attribute, the script declares the binding and reads it.
        it('writes the ref attribute the generated binding reads', () => {
            expect(result.sfc).toContain('<div ref="rootEl"');
            expect(result.sfc).toContain('const rootEl = ref(null);');
        });

        it('reports no $el warning and no manual follow-up', () => {
            expect(result.status).toBe('fully-migrated');
            expect(result.warnings).toEqual([]);
            expect(result.sfc).not.toContain('TODO: $el');
        });

        // <sw-block> hard-rejects an authored attribute, so the ref must never
        // land on it.
        it('leaves the surrounding sw-block untouched', () => {
            expect(result.sfc).toContain('<sw-block name="sw_root_el_item">');
        });
    });

    describe('instance-api-component: a block-less plain root element hosts the ref too', () => {
        it('writes the ref on the root element', () => {
            const result = mergeComponentFiles(
                readFixture('instance-api-component.html.twig'),
                readFixture('instance-api-component.index.js'),
            );

            expect(result.status).toBe('fully-migrated');
            expect(result.sfc).toContain('<div ref="rootEl" class="sw-instance-api">');
            expect(result.sfc).toContain('rootEl.value.querySelector');
        });
    });

    describe('component-root-el-component: warnings field reports $el usage', () => {
        let result: ReturnType<typeof mergeComponentFiles>;

        beforeAll(() => {
            result = mergeComponentFiles(
                readFixture('component-root-el-component.html.twig'),
                readFixture('component-root-el-component.index.js'),
            );
        });

        // A component root's `$el` is the child's root element, not this
        // component's, so a ref there would point somewhere else.
        it('reports status partially-migrated because $el has no setup equivalent', () => {
            expect(result.status).toBe('partially-migrated');
            expect(result.sfc).toContain('/* TODO: $el */');
        });

        it('populates warnings with a $el message', () => {
            expect(result.warnings).toHaveLength(1);
            expect(result.warnings[0]).toContain('$el usage detected');
        });

        it('writes no ref attribute', () => {
            expect(result.sfc).not.toContain('ref=');
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
