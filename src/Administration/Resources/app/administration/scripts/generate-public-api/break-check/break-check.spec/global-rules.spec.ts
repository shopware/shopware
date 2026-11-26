/**
 * @sw-package framework
 *
 * What the break check reports for the global `Shopware` object: a change to the members of an object, and an
 * object that comes, goes or becomes private.
 */
import { buildModel } from '../../read/build-model';
import type { SourceTree } from '../../source/source-tree';
import { OPTIONS } from '../../testing/example-components';
import { shopwareTree } from '../../testing/example-globals';
import { classifyChanges } from '../break-check';
import type { Finding } from '../finding';

function check(before: SourceTree, after: SourceTree): Finding[] {
    return classifyChanges(buildModel(before, OPTIONS), buildModel(after, OPTIONS));
}

/** One line per finding: its group, its rule, its element and its flag states. */
function summary(findings: Finding[]): string[] {
    return findings.map(({ group, rule, element, states }) => `${group}  ${rule}  ${element}  ${states.join(', ')}`);
}

/** The `Shopware` object with `Shopware.Example` as an object with this body. */
function example(body: string, before = ''): SourceTree {
    return shopwareTree(`    public Example = {\n${body}\n    };`, {}, before);
}

describe('scripts/generate-public-api/break-check/break-check global rules', () => {
    it('reports a removed method of an object by the path plugin code writes', () => {
        expect(check(example('register(name) {},\nload() {}'), example('load() {}'))).toMatchObject([
            {
                group: 'Break',
                rule: 'method_missing',
                element: 'global:Shopware.Example/method:register',
                explanation: 'The method is gone, so plugin code that calls Shopware.Example.register() throws at runtime.',
                at: 'src/core/shopware.ts:3',
            },
        ]);
    });

    it('applies the rules for the signature of a method', () => {
        const findings = check(example('register(name, options) {}'), example('register(options, name, force) {}'));

        expect(summary(findings)).toEqual([
            'Break  method_signature_changed  global:Shopware.Example/method:register  current, major',
            'Break  param_now_required  global:Shopware.Example/method:register/param:force  current, major',
        ]);
        expect(findings[0].explanation).toBe(
            'The parameters of the method changed their order from (name, options) to (options, name, force), so plugin calls of Shopware.Example.register() pass their values in the wrong place.',
        );
    });

    it('reports an object that is gone once, as a property of the object above it', () => {
        const before = shopwareTree('    public Utils = { string: { camelCase() {}, kebabCase() {} }, createId() {} };');

        expect(check(before, shopwareTree('    public Utils = { createId() {} };'))).toMatchObject([
            {
                group: 'Break',
                rule: 'property_missing',
                element: 'global:Shopware.Utils/property:string',
                explanation: 'The property is gone, so plugin code that reads Shopware.Utils.string gets undefined.',
            },
        ]);
        expect(summary(check(before, shopwareTree('')))).toEqual([
            'Break  property_missing  global:Shopware/property:Utils  current, major',
        ]);
    });

    it('reports an object that the snapshot can no longer read, although the object above it still holds it', () => {
        const utils = (string: string) => shopwareTree(`    public Utils = { string: ${string}, createId() {} };`);
        const readable = utils('{ camelCase() {}, md5() {} }');
        const unreadable = utils('Object.assign({}, { camelCase() {} })');

        expect(check(readable, unreadable)).toMatchObject([
            {
                group: 'Break',
                rule: 'global_missing',
                element: 'global:Shopware.Utils.string',
                explanation:
                    'The global is gone, so the snapshot no longer lists the members of Shopware.Utils.string, and plugin code that uses them can break unnoticed.',
                at: 'src/core/shopware.ts:3',
            },
        ]);
        expect(summary(check(unreadable, readable))).toEqual([
            'Addition  global_added  global:Shopware.Utils.string  current, major',
        ]);
    });

    it('reports a new object as a new property of the object above it', () => {
        expect(summary(check(shopwareTree(''), example('load() {}')))).toEqual([
            'Addition  property_added  global:Shopware/property:Example  current, major',
        ]);
    });

    it('reports an object that becomes private on the member that holds it, and its members as private from then on', () => {
        const becamePrivate = shopwareTree('    /** @private */\n    public Example = {\nload() {}\n    };');

        expect(summary(check(example('load() {}'), becamePrivate))).toEqual([
            'Break  visibility_became_private  global:Shopware/property:Example  current, major',
        ]);
        expect(summary(check(becamePrivate, shopwareTree('    /** @private */\n    public Example = {\n    };')))).toEqual([
            'Private  method_missing  global:Shopware.Example/method:load  current, major',
        ]);
    });

    it.each([
        [
            'is removed',
            '',
            'property_missing',
        ],
        [
            'gets a private tag on the member that holds it',
            '    /** @private */\n    public Utils = utils;',
            'visibility_became_private',
        ],
        [
            'gets a private tag on its declaration',
            '    public Utils = privateUtils;',
            'visibility_became_private',
        ],
    ])('reports an object with objects below it once when it %s', (_, after, rule) => {
        const utils = '{ string: { camelCase() {} }, array: { flatten() {} }, createId() {} }';
        const declarations = `const utils = ${utils};\n/** @private */\nconst privateUtils = ${utils};`;

        expect(
            summary(
                check(shopwareTree('    public Utils = utils;', {}, declarations), shopwareTree(after, {}, declarations)),
            ),
        ).toEqual([
            `Break  ${rule}  global:Shopware/property:Utils  current, major`,
        ]);
    });

    it('reports a method that loses its public tag in a private class as a member that became private', () => {
        const application = (tag: string) =>
            shopwareTree(
                '    public Application = application;',
                {},
                `/** @private */\nclass Bootstrapper {\n    /** @public */\n    getContainer() {}\n    /** ${tag} */\n    addServiceProvider() {}\n}\nconst application = new Bootstrapper();`,
            );

        expect(summary(check(application('@public'), application('Adds a provider.')))).toEqual([
            'Break  visibility_became_private  global:Shopware.Application/method:addServiceProvider  current, major',
        ]);
    });

    it('reports a removed method of an object that a private getter hands out as a break', () => {
        const store = (methods: string) =>
            shopwareTree(
                '    public Store = Store.instance;',
                {},
                `class Store {\n    /** @private */\n    static get instance(): Store { return Store.#instance; }\n${methods}\n}`,
            );

        expect(summary(check(store('    get(id) {}\n    list() {}'), store('    list() {}')))).toEqual([
            'Break  method_missing  global:Shopware.Store/method:get  current, major',
        ]);
    });

    it('allows the removal of a member that carried a released @deprecated marker', () => {
        const before = example('/** @deprecated tag:v6.7.0 - Use find instead. */\nload() {},\nfind() {}');

        expect(summary(check(before, example('find() {}')))).toEqual([
            'Allowed  deprecated_removal  global:Shopware.Example/method:load  current, major',
        ]);
    });
});
