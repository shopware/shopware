/**
 * @sw-package framework
 * @private
 */

import type { Accessor, AccessorImport, Dependency, Reexport } from './model';

/** Packages first, then `src/core`, `src/app`, the rest of `src/`, and paths relative to the module, as in the reference. */
const moduleGroup = (module: string) => {
    if (module.startsWith('.')) {
        return 4;
    }

    if (module.startsWith('src/core/')) {
        return 1;
    }

    if (module.startsWith('src/app/')) {
        return 2;
    }

    return module.startsWith('src/') ? 3 : 0;
};

const compareModules = (a: string, b: string) => moduleGroup(a) - moduleGroup(b) || a.localeCompare(b);

const byName = (a: string, b: string) => a.localeCompare(b, 'en', { sensitivity: 'base' });

const specifier = ({ imported, exported }: Reexport) => {
    if (imported === exported) {
        return exported;
    }

    return `${imported} as ${exported}`;
};

/** One `export … from` statement per module and kind, with the names sorted. */
const renderReexports = (reexports: Reexport[]) => {
    const statements = new Map<string, { module: string; typeOnly: boolean; names: string[]; namespace?: string }>();

    reexports.forEach((reexport) => {
        if (reexport.imported === '*') {
            statements.set(`${reexport.module}\0*\0${reexport.exported}`, {
                module: reexport.module,
                typeOnly: false,
                names: [],
                namespace: reexport.exported,
            });
            return;
        }

        const key = `${reexport.module}\0${reexport.typeOnly ? 'type' : 'value'}`;
        const statement = statements.get(key) ?? { module: reexport.module, typeOnly: !!reexport.typeOnly, names: [] };
        statement.names.push(specifier(reexport));
        statements.set(key, statement);
    });

    return [...statements.values()]
        .sort((a, b) => compareModules(a.module, b.module) || Number(a.typeOnly) - Number(b.typeOnly))
        .map(({ module, typeOnly, names, namespace }) => {
            if (namespace) {
                return `/** @private */\nexport * as ${namespace} from '${module}';`;
            }

            return `/** @private */\nexport ${typeOnly ? 'type ' : ''}{ ${[...new Set(names)].sort(byName).join(', ')} } from '${module}';`;
        });
};

const renderAccessorImports = (imports: AccessorImport[]) => {
    const byModule = new Map<string, Set<string>>();
    imports.forEach(({ module, imported }) => byModule.set(module, (byModule.get(module) ?? new Set()).add(imported)));

    return [...byModule.entries()]
        .sort(([a], [b]) => compareModules(a, b))
        .map(([module, names]) => `import { ${[...names].sort(byName).join(', ')} } from '${module}';`);
};

const renderAccessors = (accessors: Accessor[]) =>
    [...accessors]
        .sort((a, b) => a.order - b.order || byName(a.name, b.name))
        .map(({ name, body, untyped }) => {
            const exception = untyped
                ? `// eslint-disable-next-line @typescript-eslint/no-unsafe-return -- ${untyped}\n`
                : '';
            return `/** @private */\n${exception}export const ${name} = ${body};`;
        });

/**
 * Writes the dependencies.ts of a module: the names `src/` needs from outside, re-exported and grouped like the
 * reference module, then a function for everything that is looked up or changes at runtime.
 * Every export is marked `@private`, because only the module's own `src/` uses it.
 *
 * @example
 * renderDependencies('checkout', 'sw-example', [criteria, apiContext]);
 * // → "… export { default as Criteria } from 'src/core/data/criteria.data'; … export const getApiContext = …"
 */
export function renderDependencies(packageName: string, name: string, dependencies: Dependency[]): string {
    const reexports = dependencies.filter((dependency): dependency is Reexport => dependency.kind === 'reexport');
    const accessors = dependencies.filter((dependency): dependency is Accessor => dependency.kind === 'accessor');
    const imports = renderAccessorImports(accessors.flatMap((accessor) => accessor.imports));

    const sections = [
        imports.join('\n'),
        renderReexports(reexports).join('\n'),
        renderAccessors(accessors).join('\n\n'),
    ].filter(Boolean);

    return `/**
 * @sw-package ${packageName}
 *
 * Everything ${name} needs from outside its mini-module.
 */
${sections.join('\n\n')}
`;
}
