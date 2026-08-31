/**
 * @sw-package framework
 */

/**
 * The mixin → composable layer, asserted through the whole pipeline: which mixin declarations resolve,
 * what the emitted composable call and rewrites look like, and every case that keeps a component on
 * the Options API instead. The generated SFCs themselves are pinned by the fixture snapshots in
 * sfc-migration.spec.ts; these assertions name the behaviour each fixture exists for.
 */

import * as fs from 'fs';
import * as path from 'path';
import { parse } from '@babel/parser';
import type * as t from '@babel/types';
import MagicString from 'magic-string';
import { type Ctx, unwrapOptions } from './ast';
import { COMPOSABLE_DESCRIPTORS, composableCallbacks } from './composables';
import { classifyOptions, collectOwnMemberNames } from './option-handlers';
import { convertFixture, fixtureNames, fixtureScript } from './spec-helpers';

/** The options object of a fixture's default export, resolved the way transform-script does. */
function fixtureOptions(name: string): t.ObjectExpression | null {
    const body = parse(fixtureScript(name).source, { sourceType: 'module', plugins: ['typescript'] }).program.body;
    const exportDefault = body.find(
        (statement): statement is t.ExportDefaultDeclaration => statement.type === 'ExportDefaultDeclaration',
    );

    return exportDefault ? unwrapOptions(exportDefault.declaration) : null;
}

function classificationCtx(source: string, componentName: string): Ctx {
    return {
        source,
        ms: new MagicString(source),
        paths: new Map(),
        componentName,
        bindings: new Map(),
        renamedBindings: new Map(),
        templateIdentifiers: new Set(),
        templateComponentTags: new Set(),
        templateRefs: new Set(),
        helpers: new Set(),
        inferredEmits: [],
        reports: [],
    };
}

const DESCRIPTOR_FILE_IDS = fs
    .readdirSync(path.join(__dirname, 'composables', 'descriptors'))
    .filter((file) => file.endsWith('.ts') && file !== 'index.ts')
    .map((file) => path.basename(file, '.ts'));

describe('scripts/codemods/sfc-migration mixin composables', () => {
    describe('descriptor registry', () => {
        // Only descriptors/index.ts assembles the registry, so a descriptor file it does not import
        // converts nothing while looking supported. Comparing the ids against the directory catches
        // that, and a file whose name no longer matches the descriptor it holds.
        it('registers every descriptor file exactly once, under its filename', () => {
            const ids = COMPOSABLE_DESCRIPTORS.map((descriptor) => descriptor.id);

            expect([...ids].sort()).toEqual([...DESCRIPTOR_FILE_IDS].sort());
        });

        it('registers every mixin name only once', () => {
            const names = COMPOSABLE_DESCRIPTORS.flatMap((descriptor) => descriptor.mixinNames);

            expect(names).toHaveLength(new Set(names).size);
        });

        // An internally referenced member the composable does not return could never be overridden in
        // a way the guard needs to catch, so listing one is a typo rather than a policy.
        it.each(COMPOSABLE_DESCRIPTORS)('lists only real members as internally referenced for $id', (descriptor) => {
            for (const member of descriptor.internallyReferencedMembers ?? []) {
                expect(Object.keys(descriptor.members)).toContain(member);
            }
        });

        // The two lists are opposites: unmapped means the composable has no binding for the member.
        it.each(COMPOSABLE_DESCRIPTORS)('keeps the unmapped members out of the member map for $id', (descriptor) => {
            for (const member of descriptor.unmappedMembers ?? []) {
                expect(Object.keys(descriptor.members)).not.toContain(member);
            }
        });

        // An instance dependency is something the composable asks the component for, so it is by
        // definition not something the composable answers.
        it.each(COMPOSABLE_DESCRIPTORS)('keeps the instance dependencies out of the member map for $id', (descriptor) => {
            const dependencies = [
                ...(descriptor.propArgs ?? []),
                ...composableCallbacks(descriptor).map((callback) => callback.name),
            ];

            for (const dependency of dependencies) {
                expect(Object.keys(descriptor.members)).not.toContain(dependency);
            }

            // A prop cannot double as a member the composable dropped, while a callback can: the
            // mixin's own version of an overridable member is exactly what it leaves out.
            for (const prop of descriptor.propArgs ?? []) {
                expect(descriptor.unmappedMembers ?? []).not.toContain(prop);
            }
        });

        // The state keys a scaffold takes as configuration are the mixin's own, which it returns.
        it.each(COMPOSABLE_DESCRIPTORS)('lists only real members as scaffold config keys for $id', (descriptor) => {
            for (const key of descriptor.scaffold?.configKeys ?? []) {
                expect(Object.keys(descriptor.members)).toContain(key);
            }
        });

        // A prop the mixin declared reaches the component as a prop, so it is neither a member the
        // composable returns nor one it leaves unanswered.
        it.each(COMPOSABLE_DESCRIPTORS)('keeps the mixin-declared props out of the member map for $id', (descriptor) => {
            for (const provided of descriptor.providedProps ?? []) {
                expect(Object.keys(descriptor.members)).not.toContain(provided.name);
                expect(descriptor.unmappedMembers ?? []).not.toContain(provided.name);
            }
        });
    });

    it('skips a component whose mixins no composable covers', async () => {
        const result = await convertFixture('sw-mixin-demo');

        expect(result).toEqual({
            outcome: 'skipped',
            reasons: [
                "no composable registered for mixin 'sw-form-field'",
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

    // Which names a component puts on the instance is answered twice: `collectOwnMemberNames()` for
    // the override guard, `classifyOptions()` for the convertible subset. A member-creating option
    // only the second one knows about lets the guard miss an override, which converts to something
    // that compiles and behaves differently — so the guard's names stay a superset of the classified
    // ones.
    it('keeps the override guard`s member names a superset of the classified members', () => {
        const unseen = fixtureNames().flatMap((name) => {
            const options = fixtureOptions(name);

            if (!options) {
                return [];
            }

            const collected = classifyOptions(classificationCtx(fixtureScript(name).source, name), options);
            const ownMembers = collectOwnMemberNames(options);

            return [
                ...collected.propNames,
                ...collected.dataEntries.map((entry) => entry.name),
                ...collected.computeds.map((computed) => computed.name),
                ...collected.methods.map((method) => method.name),
                ...collected.injects,
            ]
                .filter((member) => !ownMembers.has(member))
                .map((member) => `${name}: ${member}`);
        });

        expect(unseen).toEqual([]);
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
            'sw-mixin-cms-element-service',
            "'cmsService' is read but the 'cms-element' composable does not provide it",
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

        // The generated name is the codemod's, not the developer's, so the draft asks for a look.
        expect(result.outcome).toBe('partial');
        expect(result.reasons).toEqual([
            "'salutation' was renamed to 'salutation$1' — its name is already taken by another binding",
        ]);
        expect(result.sfc).toContain('const { salutation: salutation$1 } = useSalutation();');

        // The module-level helper keeps every bare reference; only `this.salutation` moves.
        expect(result.sfc).toContain('return salutation(props.customer);');
        expect(result.sfc).toContain("return salutation$1(props.customer, 'no name');");

        // swDefinePublic takes shorthand bindings only, so the renamed member stays private
        // instead of being published under the generated name.
        expect(result.sfc).not.toContain('salutation$1,');
    });

    // The rename is a local decision about one declaration, so the TODO sits with it instead of in
    // the file's trailing TODO list.
    it('flags the rename directly above the destructure it applies to', async () => {
        const result = await convertFixture('sw-mixin-collision');

        expect(result.sfc).toContain(
            [
                "// TODO(sfc-migration) VERIFY: 'salutation' was renamed to 'salutation$1' — its name is already taken by another binding",
                '// The draft runs as emitted; a renamed member stays out of swDefinePublic, so rename it and its uses to have it public or prettier',
                'const { salutation: salutation$1 } = useSalutation();',
            ].join('\n'),
        );
    });

    describe('instance dependencies', () => {
        it('declares the mixin events and hands emit over as intent-named callbacks', async () => {
            const result = await convertFixture('sw-mixin-emits');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain(
                "const emit = defineEmits(['media-sidebar-items-delete', 'media-sidebar-folder-items-dissolve', 'media-sidebar-items-move']);",
            );
            expect(result.sfc).toContain("onItemsDelete: (...args) => emit('media-sidebar-items-delete', ...args)");

            // The flag the mixin owned is a ref the template reads.
            expect(result.sfc).toContain('showModalDelete');
        });

        it('merges the mixin events into the component`s own emits list without repeating one', async () => {
            const result = await convertFixture('sw-mixin-emits-declared');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain(
                "const emit = defineEmits([\n    'media-sidebar-items-delete',\n    'selection-cleared',\n    'media-sidebar-folder-items-dissolve',\n    'media-sidebar-items-move',\n]);",
            );
        });

        it('passes a declared prop as a getter', async () => {
            const result = await convertFixture('sw-mixin-prop-getter');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('useVideoCover({\n    item: () => props.item,\n});');
            expect(result.sfc).toContain('const props = defineProps(');
        });

        it('passes the component`s own member as the callback the mixin expected', async () => {
            const result = await convertFixture('sw-mixin-callback');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('selectableItems: () => selectableItems.value');

            // The callback points at a computed declared below the call, so it has to stay deferred.
            expect(result.sfc?.indexOf('useMediaGridListener(')).toBeLessThan(
                result.sfc?.indexOf('const selectableItems = computed(') as number,
            );

            // A ref-backed member still takes a write.
            expect(result.sfc).toContain('selectedItems.value = [];');
        });

        it('merges the props the mixin declared into the component`s own defineProps', async () => {
            const result = await convertFixture('sw-mixin-provided-props');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);

            // The component's own prop keeps its place, the mixin's four follow it.
            expect(result.sfc).toContain('entity: {');
            expect(result.sfc).toContain('condition: {');
            expect(result.sfc).toContain('parentCondition: {');
            expect(result.sfc).toContain('level: {');
            expect(result.sfc).toContain('disabled: {');

            // A merged prop is a prop: it feeds the composable's getters and rewrites like one.
            expect(result.sfc).toContain('condition: () => props.condition,');
            expect(result.sfc).toContain('props.condition.id');
            expect(result.sfc).toContain('removeNodeFromTree(props.parentCondition, props.condition)');
        });

        it('passes a method the mixin called as a forwarding call', async () => {
            const result = await convertFixture('sw-mixin-callback-method');

            expect(result.outcome).toBe('full');
            expect(result.reasons).toEqual([]);
            expect(result.sfc).toContain('ensureValueExist: (...args) => ensureValueExist(...args)');
        });

        it.each([
            [
                'sw-mixin-callback-not-method',
                "'ensureValueExist' is not a method, but the 'rule-between-operator' composable calls it",
            ],
            [
                'sw-mixin-emits-object',
                "emits is not a plain list of event names, so the 'media-sidebar-modal' mixin's events cannot be merged",
            ],
            [
                'sw-mixin-missing-prop',
                "component does not declare the 'item' prop the 'video-cover' mixin reads",
            ],
            [
                'sw-mixin-missing-callback',
                "component does not define 'selectableItems', which the 'media-grid-listener' composable calls",
            ],
            [
                'sw-mixin-member-assign',
                "'handleMediaItemClicked' is assigned to, but the 'media-grid-listener' composable returns it as a constant",
            ],
            [
                'sw-mixin-props-spread',
                "props are not a plain object literal, so the 'validation' mixin's props cannot be merged",
            ],
        ])('skips %s, whose instance dependency the codemod cannot wire', async (name, reason) => {
            const result = await convertFixture(name);

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toContain(reason);
            expect(result.sfc).toBeNull();
        });
    });

    describe('scaffold tier', () => {
        it('wires a listing component up to useListing and leaves it as a draft', async () => {
            const result = await convertFixture('sw-mixin-listing-scaffold');

            // A scaffold is never finished work, however clean the output looks.
            expect(result.outcome).toBe('partial');
            expect(result.reasons).toContain("useListing() replaces the 'listing' mixin");

            // The review checklist leads the draft, and says that it is a review rather than a repair.
            expect(result.sfc).toContain("// TODO(sfc-migration) VERIFY: useListing() replaces the 'listing' mixin");
            expect(result.sfc).toContain('// Nothing is missing from the draft;');
            expect(result.sfc).toContain('// - the initial load runs on mounted now');
            expect(result.sfc).toContain('// - these were routed into the composable options');

            // The component's own getList is what the composable drives, under its own name, so the
            // component's call sites and its public surface both keep working.
            expect(result.sfc).toContain('getList: (...args) => getList(...args)');
            expect(result.sfc).toMatch(/const getList =[\s\S]*?async function \(\) \{/);
            expect(result.sfc).toContain('const onSaved = function () {\n    getList();\n};');
            expect(result.sfc).toContain('getList,');

            // Listing state the component only configured is handed over instead of declared.
            expect(result.sfc).toContain('limit: 10');
            expect(result.sfc).toContain("sortBy: 'createdAt'");
            expect(result.sfc).toContain("searchConfigEntity: 'product'");
            expect(result.sfc).not.toContain('const limit = ref(10);');

            // What is left of the mixin's state is read through the destructured refs.
            expect(result.sfc).toContain('new Criteria(page.value, limit.value)');
            expect(result.sfc).toContain('total.value = result.total;');

            // The component's `filters` override reaches the composable as the getter it takes.
            expect(result.sfc).toContain('filters: () => filters.value');
        });

        it('wires a cms element component up to the deprecated composable and leaves it as a draft', async () => {
            const result = await convertFixture('sw-mixin-cms-element-scaffold');

            expect(result.outcome).toBe('partial');
            expect(result.reasons).toEqual(["useCmsElementDeprecated() replaces the 'cms-element' mixin"]);

            expect(result.sfc).toContain(
                "// TODO(sfc-migration) VERIFY: useCmsElementDeprecated() replaces the 'cms-element' mixin",
            );
            expect(result.sfc).toContain('// - the config writes still reach the element object itself');

            // The props the mixin declared are merged in, and the two the composable reads are handed
            // back to it as getters.
            expect(result.sfc).toContain('element: {');
            expect(result.sfc).toContain('defaultConfig: {');
            expect(result.sfc).toContain('disabled: {');
            expect(result.sfc).toContain('useCmsElementDeprecated({\n    element: () => props.element,');
            expect(result.sfc).toContain('defaultConfig: () => props.defaultConfig,');

            // The in-place write the CMS editor depends on stays exactly that.
            expect(result.sfc).toContain('props.element.config.content.value = content;');

            // A component that names only `cms-element` reads the cms state through it.
            expect(result.sfc).toContain('cmsPageState,');
            expect(result.sfc).toContain('() => cmsPageState.value.currentDemoEntity,');
        });

        it.each([
            [
                'sw-mixin-listing-no-get-list',
                "component does not define 'getList', which the 'listing' composable calls",
            ],
            [
                'sw-mixin-listing-wrapped-get-list',
                "'getList' is declared in a shape that cannot be handed to the 'listing' composable",
            ],
        ])('skips %s, which has no getList the codemod can hand over', async (name, reason) => {
            const result = await convertFixture(name);

            expect(result.outcome).toBe('skipped');
            expect(result.reasons).toContain(reason);
            expect(result.sfc).toBeNull();
        });
    });

    it('backs off from the legacy $t/$tc argument shapes and rewrites the portable ones', async () => {
        const result = await convertFixture('sw-legacy-i18n');

        expect(result.outcome).toBe('partial');
        expect(result.reasons).toEqual(
            expect.arrayContaining([
                'this.$t(key, locale) is left as authored and does not run in setup',
                'this.$tc(key, choice, values) is left as authored and does not run in setup',
            ]),
        );

        expect(result.sfc).toContain("t('sw-legacy-i18n.title', { name: 'demo' })");
        expect(result.sfc).toContain("t('sw-legacy-i18n.items', props.itemCount)");
        expect(result.sfc).toContain("t('sw-legacy-i18n.toggle', props.collapsed ? 0 : 1)");

        // The TODO says that the call has to be written by hand, not just reviewed.
        expect(result.sfc).toContain(
            '// TODO(sfc-migration) FIX: this.$t(key, locale) is left as authored and does not run in setup',
        );
        expect(result.sfc).toContain('// Composition t() would read the locale as a default message');

        // The refused calls keep their `this.` callee, but their arguments still rewrite.
        expect(result.sfc).toContain("this.$t('sw-legacy-i18n.title', Shopware.Context.app.fallbackLocale)");
        expect(result.sfc).toContain("this.$tc('sw-legacy-i18n.items', props.itemCount, { name: 'demo' })");
    });
});
