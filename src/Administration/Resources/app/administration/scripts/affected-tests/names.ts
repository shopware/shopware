/**
 * @sw-package framework
 * @private
 */

/** What a name stands for when code looks it up at runtime instead of importing it. */
export type NameKind = 'component' | 'mixin' | 'service' | 'store' | 'filter' | 'directive' | 'module';

/** A name a source file registers, like `{ kind: 'component', name: 'sw-foo' }`. */
export type RegisteredName = { kind: NameKind; name: string };

/**
 * What a source file registers: the literal names, and the calls whose name exists only at runtime.
 * Use it to find specs that reach a file by name instead of by import.
 */
export type Registrations = { names: RegisteredName[]; dynamic: string[] };

/** The calls that put something into a global registry; the name argument follows the match. */
const REGISTRATION_CALLS: ReadonlyArray<{ kind: NameKind; call: RegExp }> = [
    { kind: 'component', call: /Component\.(?:register|extend|override)\(\s*/g },
    { kind: 'mixin', call: /Mixin\.register\(\s*/g },
    { kind: 'service', call: /(?:Service\(\)\.register|addServiceProvider(?:Decorator)?)\(\s*/g },
    { kind: 'store', call: /Store\.register\(\s*(?:\{\s*id:\s*)?/g },
    { kind: 'filter', call: /Filter\.register\(\s*/g },
    { kind: 'directive', call: /Directive\.register\(\s*/g },
    { kind: 'module', call: /Module\.register\(\s*/g },
];

const STRING_LITERAL = /^(['"`])([^'"`$\n]+)\1/;

/** Block comments and line comments, so examples in doc comments do not count as registrations. */
const COMMENTS = /\/\*[\s\S]*?\*\/|(?<![:'"`\\])\/\/.*$/gm;

/**
 * Finds the names a source file registers in Shopware's global registries.
 * A registration whose name is not a string literal lands in `dynamic`, because no static scan can tell its name.
 *
 * @example
 * findRegistrations("Shopware.Component.register('sw-foo', () => import('./sw-foo'));");
 * // → { names: [{ kind: 'component', name: 'sw-foo' }], dynamic: [] }
 * findRegistrations('Shopware.Service().register(serviceName, factory);');
 * // → { names: [], dynamic: ['Service().register(serviceName'] }
 */
export function findRegistrations(source: string): Registrations {
    const code = source.replace(COMMENTS, '');
    const registrations: Registrations = { names: [], dynamic: [] };

    REGISTRATION_CALLS.forEach(({ kind, call }) => {
        for (const match of code.matchAll(call)) {
            const rest = code.slice(match.index + match[0].length);
            const literal = STRING_LITERAL.exec(rest);

            if (literal) {
                registrations.names.push({ kind, name: literal[2] });
            } else {
                registrations.dynamic.push(`${match[0].trim()}${rest.split(/[,)\n]/)[0]}`);
            }
        }
    });

    return registrations;
}

/**
 * Reads the component name from a mini-module's `index.ts`, which declares it with `definePublicComponent()`,
 * or from the `private.ts` of a registered private module, which uses `definePrivateComponent()`.
 * Returns null when the module is no component, or a nested one without a name.
 *
 * @example
 * findPublicComponentName("export default definePublicComponent({ name: 'sw-example', load: ... });");
 * // → 'sw-example'
 */
export function findPublicComponentName(indexSource: string): string | null {
    return /define(?:Public|Private)Component\(\s*\{[\s\S]*?\bname:\s*(['"])([^'"]+)\1/.exec(indexSource)?.[2] ?? null;
}

/**
 * Whether a source mentions a name as a whole word, so `sw-foo` matches `'sw-foo'` and `<sw-foo>`
 * but not `sw-foo-bar` or `.sw-foo__label`.
 *
 * @example
 * mentionsName("await wrapTestComponent('sw-foo')", 'sw-foo'); // → true
 * mentionsName("await wrapTestComponent('sw-foo-bar')", 'sw-foo'); // → false
 */
export function mentionsName(source: string, name: string): boolean {
    const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return new RegExp(`(?<![\\w$-])${escaped}(?![\\w$-])`).test(source);
}
