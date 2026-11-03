/**
 * @sw-package framework
 * @private
 */

import type { OutsideEdit, UseRewrite } from '../model';
import { camelCase, upperFirst } from '../model';
import { isPath, literalArgument, replaceNode, untypedNote } from './helpers';

/**
 * `Shopware.Filter.getByName('x')` becomes a function in dependencies.ts that looks the filter up by the name its
 * file exports, because an extension may register filters later. A filter file without an exported name gets one.
 *
 * @example
 * // const dateFilter = Shopware.Filter.getByName('date');
 * // → const dateFilter = getDateFilter();
 * // dependencies.ts: export const getDateFilter = () => Shopware.Filter.getByName(dateFilterName);
 */
export const rewriteFilter: UseRewrite = (use, context) => {
    const filterName = literalArgument(use.call);
    const filter = filterName === null || !isPath(use, 'Filter', 'getByName') ? null : context.index.findFilter(filterName);
    if (!use.call || !filterName || !filter) {
        return null;
    }

    const exportName = filter.exportName ?? `${camelCase(filterName)}FilterName`;
    const name = `get${upperFirst(camelCase(filterName))}Filter`;
    const typing = context.index.typing('FilterTypes', filterName);
    if (!typing || !context.isFree(name)) {
        return null;
    }

    const outsideEdits: OutsideEdit[] = filter.exportName
        ? []
        : [{ kind: 'filter-name', file: filter.file, name: filterName, exportName }];

    return replaceNode(
        use.call,
        context,
        `${name}()`,
        [
            {
                kind: 'accessor',
                name,
                order: 2,
                body: `() => Shopware.Filter.getByName(${exportName})`,
                imports: [{ module: filter.module, imported: exportName }],
                untyped: untypedNote(typing, 'FilterTypes', filterName),
            },
        ],
        outsideEdits,
    );
};
