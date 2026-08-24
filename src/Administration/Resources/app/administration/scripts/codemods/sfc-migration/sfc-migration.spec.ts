/**
 * @sw-package framework
 */

import { convertComponent } from './convert-component';
import { OPTION_HANDLERS } from './option-handlers';
import { convertFixture, fixtureNames, templateImportRange } from './spec-helpers';
import { LIFECYCLE_HOOKS, OPTION_TIERS } from './tables';
import { TWIG_PARENT_BLOCKER, transformTemplate } from './transform-template';

describe('scripts/codemods/sfc-migration', () => {
    describe('fixture snapshots (outcome, reasons and generated SFC per fixture)', () => {
        it.each(fixtureNames())('converts %s', async (name) => {
            const result = await convertFixture(name);

            expect(result).toMatchSnapshot();
        });
    });

    // classifyOptions() reads OPTION_TIERS before dispatching, so an option in both tables would
    // never reach its handler.
    it('assigns each option either a tier or a handler, never both', () => {
        const dispatched = [
            ...Object.keys(OPTION_HANDLERS),
            ...Object.keys(LIFECYCLE_HOOKS),
        ];

        expect(dispatched.filter((option) => option in OPTION_TIERS)).toEqual([]);
    });

    describe('outcome expectations (guard the snapshots against silent regressions)', () => {
        it('keeps array injection conservative until its ref-unwrapping contract is proven', async () => {
            const result = await convertFixture('sw-simple-card');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain('array inject declaration requires runtime ref-unwrapping verification');
            expect(result.sfc).toContain('swDefinePublic({');
            expect(result.sfc).not.toContain('this.');
            expect(result.sfc).not.toContain('$dataScope');
        });

        it('marks TODO-tier features as partial but still emits a valid draft', async () => {
            const result = await convertFixture('sw-partial-todos');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    expect.stringContaining('inject'),
                    expect.stringContaining('metaInfo'),
                    expect.stringContaining('shortcuts'),
                    expect.stringContaining('$device'),
                ]),
            );
            expect(result.sfc).toContain('TODO(sfc-migration)');
        });

        it('leaves a this.<member> shadowed by a local binding unrewritten', async () => {
            const result = await convertFixture('sw-shadowed-locals');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(
                expect.arrayContaining([
                    'this.currentPage is shadowed by a local binding',
                    'this.perPage is shadowed by a local binding',
                    'this.iconSvgData is shadowed by a local binding',
                    'this.$route is shadowed by a local binding',
                    "template ref 'modalContent' is shadowed by a local binding",
                ]),
            );

            // The shadowed references keep their original text instead of resolving to the local.
            expect(result.sfc).toContain('this.perPage = Number(perPage)');
            expect(result.sfc).not.toContain('perPage.value = Number(perPage)');
            expect(result.sfc).toContain('const currentPage = this.currentPage');
            expect(result.sfc).not.toContain('const currentPage = currentPage.value');

            // A shadowed template ref must not be declared either — nothing would ever assign it.
            expect(result.sfc).not.toContain('const modalContent = ref(null)');

            // A binding in a sibling nested function does not shadow, and a local named after a
            // prop cannot shadow `props.<name>`.
            expect(result.sfc).toContain('items.value');
            expect(result.sfc).toContain('props.title');
        });

        it('preserves module-level code in a normal script block', async () => {
            const result = await convertFixture('sw-module-level-code');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('<script data-sfc-migration-module lang="ts">');
        });

        // The `const { X } = Shopware` prelude is by far the most common shape in src/; widening the
        // allowlist check to reject it would downgrade more than half of all components.
        it('keeps a pure Shopware-namespace prelude a full migration', async () => {
            const result = await convertFixture('sw-wrap-config');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain('array inject declaration requires runtime ref-unwrapping verification');
        });

        it('skips components using this.$super', async () => {
            const result = await convertFixture('sw-super-demo');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(['this.$super']);
        });

        it('skips components whose name option differs from the directory name', async () => {
            const result = await convertFixture('sw-name-mismatch');

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toEqual(["name 'sw-totally-different' does not match the directory name"]);
        });

        it('reconnects a v-if/v-else chain that the block conversion split into siblings', async () => {
            const result = await convertFixture('sw-cross-velse');

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain(
                '<template v-if="active"><!-- Keeps the conditional chain connected across sw-block. --></template>',
            );
        });

        // Every authoring form has to be refused: the leftover-twig check only looks for `{%`/`{#`,
        // so a surviving `{{ parent() }}` would compile as a live interpolation and fail at runtime.
        it.each([
            '{% block a_b %}{% parent %}{% endblock %}',
            '{% block a_b %}{{ parent() }}{% endblock %}',
            '{% block a_b %}{%- parent -%}{% endblock %}',
        ])('refuses %s, which only base output cannot express', (twig) => {
            expect(transformTemplate(twig)).toEqual({ template: null, blockers: [TWIG_PARENT_BLOCKER] });
        });

        // A `-->` in the body would close the generated comment early and spill the rest into
        // rendered markup — output Vue parses happily, so nothing downstream would catch it.
        it.each([
            [
                '{# see --> here #}',
                '<!-- see -- > here -->',
            ],
            [
                '{# see --!> here #}',
                '<!-- see -- !> here -->',
            ],
            [
                '{# arrow ---> tail #}',
                '<!-- arrow --- > tail -->',
            ],
        ])('converts %s without letting the comment terminate early', (twig, expected) => {
            const result = transformTemplate(`<div>${twig}<span>kept</span></div>`);

            expect(result.blockers).toEqual([]);
            expect(result.template).toBe(`<div>${expected}<span>kept</span></div>`);
        });

        // The block keeps its name and its position around the slot content, so an override still
        // targets exactly what it targeted before the inversion.
        it('hoists a named slot out of the twig block that wrapped it', async () => {
            const result = await convertFixture('sw-block-named-slot');

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain('<template #modal-footer>');
            expect(result.sfc?.indexOf('<template #modal-footer>')).toBeLessThan(
                result.sfc!.indexOf('<sw-block name="sw_block_named_slot_footer">'),
            );
        });

        it('skips a component whose twig uses {% parent %}', async () => {
            const result = await convertFixture('sw-twig-parent');

            expect(result).toEqual({ outcome: 'skipped', reasons: [TWIG_PARENT_BLOCKER], sfc: null });
        });

        it('keeps a named slot that belongs to a child component inside the block', async () => {
            const result = await convertFixture('sw-slot-in-child');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('#modal-footer');
        });

        it('preserves function contracts and special JSDoc when rendering setup functions', async () => {
            const specialJsDoc =
                '@deprecated tag:v6.8.0 @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES @internal @private';
            const jsSource = `
                    import template from './sw-function-contracts.html.twig';

                    export default {
                        template,
                        methods: {
                            /** ${specialJsDoc} */
                            typed<T>(value: T): T {
                                return value;
                            },
                        },
                    };
                `;
            const result = await convertComponent({
                componentName: 'sw-function-contracts',
                jsSource,
                twigSource: '{% block sw_function_contracts %}<div />{% endblock %}',
                vuePath: 'sw-function-contracts.vue',
                lang: 'ts',
                templateImportRange: templateImportRange(jsSource),
            });

            expect(result.outcome).toBe('full');
            expect(result.sfc).toContain(specialJsDoc);
            expect(result.sfc).toMatch(
                new RegExp(`const typed =\\s+/\\*\\* ${specialJsDoc} \\*/\\s+function <T>\\(value: T\\): T`),
            );
        });
    });
});
