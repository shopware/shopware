/**
 * @sw-package framework
 */

/**
 * The mixin → composable layer, asserted through the whole pipeline: which mixin declarations resolve,
 * what the emitted composable call and rewrites look like, and every case that keeps a component on
 * the Options API instead. The generated SFCs themselves are pinned by the fixture snapshots in
 * sfc-migration.spec.ts; these assertions name the behaviour each fixture exists for.
 */

import { convertFixture } from './spec-helpers';

describe('scripts/codemods/sfc-migration mixin composables', () => {
    it('skips a component whose mixins no composable covers', async () => {
        const result = await convertFixture('sw-mixin-demo');

        expect(result).toEqual({
            outcome: 'skipped',
            reasons: [
                "no composable registered for mixin 'listing'",
                "unsupported mixins entry 'swListMixin'",
            ],
            sfc: null,
        });
    });

    it('resolves the getByName form, one composable per mixin, and members only the template reads', async () => {
        const result = await convertFixture('sw-mixin-composable');

        expect(result.outcome).toBe('full');
        expect(result.reasons).toEqual([]);
        expect(result.sfc).toContain("import useNotification from 'src/app/composables/use-notification';");
        expect(result.sfc).toContain("import useSalutation from 'src/app/composables/use-salutation';");
        expect(result.sfc).toContain('const { createNotificationSuccess } = useNotification();');

        // `salutation` appears in the template only, so nothing rewrote a reference to it — the
        // declaration exists because the template needs the binding.
        expect(result.sfc).toContain('const { salutation } = useSalutation();');
        expect(result.sfc).toContain('salutation,');

        // Members the component never touches are not destructured.
        expect(result.sfc).not.toContain('createNotificationError');
    });

    it('resolves the string form and lets a component member shadow an unmapped mixin member', async () => {
        const result = await convertFixture('sw-mixin-string-form');

        expect(result.outcome).toBe('full');
        expect(result.reasons).toEqual([]);
        expect(result.sfc).toContain('const { salutation } = useSalutation();');
        expect(result.sfc).toContain('const salutationFilter = computed(');
    });

    it.each([
        [
            'sw-mixin-override',
            "component redefines 'createNotificationSuccess' from the 'notification' mixin",
        ],
        [
            'sw-mixin-internal-override',
            "component redefines 'createNotification', which the 'notification' composable calls internally",
        ],
        [
            'sw-mixin-unmapped',
            "'salutationFilter' is read but the 'salutation' composable does not provide it",
        ],
        [
            'sw-mixin-template-collision',
            "'salutation' is read in the template and its binding name is already taken",
        ],
    ])('skips %s, whose mixin members the composable cannot stand in for', async (name, reason) => {
        const result = await convertFixture(name);

        expect(result).toEqual({ outcome: 'skipped', reasons: [reason], sfc: null });
    });

    it('renames a composable member around a module-level binding of the same name', async () => {
        const result = await convertFixture('sw-mixin-collision');

        expect(result.outcome).toBe('full');
        expect(result.reasons).toEqual([]);
        expect(result.sfc).toContain('const { salutation: salutation$1 } = useSalutation();');

        // The module-level helper keeps every bare reference; only `this.salutation` moves.
        expect(result.sfc).toContain('return salutation(props.customer);');
        expect(result.sfc).toContain("return salutation$1(props.customer, 'no name');");

        // swDefinePublic takes shorthand bindings only, so the renamed member stays private
        // instead of being published under the generated name.
        expect(result.sfc).not.toContain('salutation$1,');
    });

    it('backs off from the legacy $t/$tc argument shapes and rewrites the portable ones', async () => {
        const result = await convertFixture('sw-legacy-i18n');

        expect(result.outcome).toBe('partial');
        expect(result.reasons).toEqual(
            expect.arrayContaining([
                'legacy this.$t(key, locale) argument shape',
                'legacy this.$tc(key, choice, values) argument order',
            ]),
        );

        expect(result.sfc).toContain("t('sw-legacy-i18n.title', { name: 'demo' })");
        expect(result.sfc).toContain("t('sw-legacy-i18n.items', props.itemCount)");

        // The refused calls keep their `this.` callee, but their arguments still rewrite.
        expect(result.sfc).toContain("this.$t('sw-legacy-i18n.title', Shopware.Context.app.fallbackLocale)");
        expect(result.sfc).toContain("this.$tc('sw-legacy-i18n.items', props.itemCount, { name: 'demo' })");
    });
});
