/**
 * @sw-package framework
 *
 * Reads the global interfaces that give the registered names a type, such as `ServiceContainer` and
 * `PiniaRootState`. Any file may extend one of them.
 */
import ts from 'typescript';
import type { SourceTree } from '../source/source-tree';
import type { Declarations } from './declarations';
import { collect, memberName } from './script-nodes';

/**
 * @private
 *
 * The type the interface declares for each of its entries, as the source writes it, across all files.
 */
export function interfaceEntries(name: string, tree: SourceTree, declarations: Declarations): Map<string, string> {
    const entries = tree.files
        .filter((file) => file.endsWith('.ts') && tree.read(file).includes(`interface ${name}`))
        .flatMap((file) => {
            const source = declarations.parse(file);

            return collect(
                source,
                (node): node is ts.InterfaceDeclaration => ts.isInterfaceDeclaration(node) && node.name.text === name,
            )
                .flatMap(({ members }) => members.filter(ts.isPropertySignature))
                .flatMap((entry): [string, string][] => {
                    const entryName = memberName(entry);

                    return entryName === null ? [] : [[entryName, entry.type?.getText(source) ?? 'any']];
                });
        });

    return new Map(entries);
}
