/**
 * @sw-package framework
 *
 * What the break check reports for a store: one that is gone, new or private, and a change to its state, its
 * getters and its actions.
 */
import { buildModel } from '../../read/build-model';
import type { SourceTree } from '../../source/source-tree';
import { OPTIONS, treeOf } from '../../testing/example-components';
import { optionsTree, STORE_FILE } from '../../testing/example-stores';
import { classifyChanges } from '../break-check';
import type { Finding } from '../finding';

const NO_STORE = treeOf({ [STORE_FILE]: '' });

function check(before: SourceTree, after: SourceTree): Finding[] {
    return classifyChanges(buildModel(before, OPTIONS), buildModel(after, OPTIONS));
}

/** One line per finding: its group, its rule, its element and its flag states. */
function summary(findings: Finding[]): string[] {
    return findings.map(({ group, rule, element, states }) => `${group}  ${rule}  ${element}  ${states.join(', ')}`);
}

/** The example store with these actions. */
function actions(...declarations: string[]): SourceTree {
    return optionsTree(`actions: {\n${declarations.join(',\n')}\n}`);
}

describe('scripts/generate-public-api/break-check/break-check store rules', () => {
    describe('a store as a whole', () => {
        it('reports a store that is gone as a break, because getting it throws', () => {
            expect(check(optionsTree(''), NO_STORE)).toEqual([
                {
                    group: 'Break',
                    rule: 'store_missing',
                    element: 'store:example',
                    states: ['current', 'major'],
                    explanation:
                        "The store is gone, so plugin code that calls Shopware.Store.get('example') throws at runtime.",
                    fix: 'pattern "Removal". Keep the store and mark it /** @deprecated tag:v6.8.0 - Will be removed. */.',
                },
            ]);
        });

        it('allows the removal of a store that carried a released @deprecated marker', () => {
            const deprecated = optionsTree('', '/** @deprecated tag:v6.7.0 - Will be removed. */');

            expect(summary(check(deprecated, NO_STORE))).toEqual([
                'Allowed  deprecated_removal  store:example  current, major',
            ]);
        });

        it('reports a new store as an addition, and a private one that comes or goes as private', () => {
            const privateStore = optionsTree('', '/** @private */');

            expect(summary(check(NO_STORE, optionsTree('')))).toEqual([
                'Addition  store_added  store:example  current, major',
            ]);
            expect(summary([...check(privateStore, NO_STORE), ...check(NO_STORE, privateStore)])).toEqual([
                'Private  store_missing  store:example  current, major',
                'Private  store_added  store:example  current, major',
            ]);
        });

        it('reports a store that becomes private', () => {
            expect(check(optionsTree(''), optionsTree('', '/** @private */'))).toMatchObject([
                {
                    group: 'Break',
                    rule: 'visibility_became_private',
                    element: 'store:example',
                    explanation:
                        "The store became @private, which removes it from the public API, so plugin code that uses Shopware.Store.get('example') can break with any later change.",
                },
            ]);
        });
    });

    describe('the members of a store', () => {
        it('reports a removed state property, getter and action in the words of a store access', () => {
            const findings = check(
                optionsTree('state: () => ({ items: [] }), getters: { total() {} }, actions: { load() {} }'),
                optionsTree('state: () => ({}), getters: {}, actions: {}'),
            );

            expect(findings.map(({ rule, element, explanation }) => ({ rule, element, explanation }))).toEqual([
                {
                    rule: 'action_missing',
                    element: 'store:example/action:load',
                    explanation:
                        "The action is gone, so plugin code that calls Shopware.Store.get('example').load() throws at runtime.",
                },
                {
                    rule: 'getter_missing',
                    element: 'store:example/getter:total',
                    explanation:
                        "The getter is gone, so plugin code that reads Shopware.Store.get('example').total gets undefined.",
                },
                {
                    rule: 'state_missing',
                    element: 'store:example/state:items',
                    explanation:
                        "The state property is gone, so plugin code that reads Shopware.Store.get('example').items gets undefined.",
                },
            ]);
        });

        it('points a finding about a removed member to the option that held it', () => {
            const [finding] = check(
                optionsTree('\nstate: () => ({}),\nactions: {\nload() {},\nreset() {} }'),
                optionsTree('\nstate: () => ({}),\nactions: {\nreset() {} }'),
            );

            expect(finding.at).toBe('src/app/store/example.store.ts:4');
        });

        it('applies the rules for the signature of a method to an action, and names the action', () => {
            const findings = check(
                actions('load(id, force) {}', 'save(item) {}', 'find(term): Item[] {}', 'list(limit = 25) {}'),
                actions('load(force, id) {}', 'async save(item, options) {}', 'find(term): Item {}', 'list(limit = 50) {}'),
            );

            expect(summary(findings)).toEqual([
                'Break  method_signature_changed  store:example/action:load  current, major',
                'Break  method_signature_changed  store:example/action:save  current, major',
                'Break  param_default_changed  store:example/action:list/param:limit  current, major',
                'Break  param_now_required  store:example/action:save/param:options  current, major',
                'Break  return_type_changed  store:example/action:find  current, major',
            ]);
            expect(findings.map(({ explanation }) => explanation).slice(0, 2)).toEqual([
                "The parameters of the action changed their order from (id, force) to (force, id), so plugin calls of Shopware.Store.get('example').load() pass their values in the wrong place.",
                "The action now returns a Promise, so plugin code that uses the return value of Shopware.Store.get('example').save() gets a Promise instead.",
            ]);
            expect(findings[2].fix).toBe(
                'keep the default, or mark the action @bc-change ParameterDefaultValueChange version:v6.8.0 parameter:limit default:50.',
            );
        });

        it('allows a removed parameter of an action that a released @bc-change marker names', () => {
            const before = actions('/** @bc-change ParameterRemoval version:v6.7.0 parameter:force */\nload(id, force) {}');
            const [finding] = check(before, actions('load(id) {}'));

            expect(finding).toMatchObject({
                group: 'Allowed',
                rule: 'announced_change',
                explanation: 'The action carries @bc-change ParameterRemoval version:v6.7.0, which announced this change.',
            });
        });

        it('reports a changed type of a state property and of a getter', () => {
            const findings = check(
                optionsTree('state: () => ({ limit: 25 as number, label: "" }), getters: { total(): number {} }'),
                optionsTree('state: () => ({ limit: 25 as string, label: "" as string }), getters: { total(): string {} }'),
            );

            expect(summary(findings)).toEqual([
                'Break  return_type_changed  store:example/getter:total  current, major',
                'Break  type_changed  store:example/state:limit  current, major',
                'Info  type_widened  store:example/state:label  current, major',
            ]);
        });

        it('reports a getter that became state as a getter that is gone and a new state property', () => {
            const findings = check(optionsTree('getters: { total() {} }'), optionsTree('state: () => ({ total: 0 })'));

            expect(summary(findings)).toEqual([
                'Break  getter_missing  store:example/getter:total  current, major',
                'Addition  state_added  store:example/state:total  current, major',
            ]);
        });

        it('lists a change to a private member as private, and reports nothing for a change inside an action', () => {
            expect(summary(check(actions('/** @private */\nload(id) {}'), actions('/** @private */\nload() {}')))).toEqual([
                'Private  param_missing  store:example/action:load/param:id  current, major',
            ]);
            expect(check(actions('load(id) { return 1; }'), actions('load(id) { return this.find(id); }'))).toEqual([]);
        });
    });
});
