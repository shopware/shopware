/**
 * @sw-package framework
 * @private
 */

import type { OutsideEdit, UseRewrite } from '../model';
import { isIdentifierName, upperFirst } from '../model';
import { isPath, literalArgument, replaceNode, accessorHead } from './helpers';

/** Where the constants of the service names live. */
export const SERVICE_NAMES = 'src/app/service-names';

/** The new constant in src/app/service-names that a service name needs, or none when it is there. */
export const serviceNameEdits = (name: string, hasServiceName: boolean): OutsideEdit[] =>
    hasServiceName ? [] : [{ kind: 'service-name', name }];

/**
 * `Shopware.Service('x')` becomes a function in dependencies.ts that looks the service up by the constant from
 * src/app/service-names, because services are registered, and may be decorated, at runtime.
 * A service the global `ServiceContainer` type does not list stays, because TypeScript would reject the lookup.
 *
 * @example
 * // const service = Shopware.Service('shopwareServicesService');
 * // → const service = getShopwareServicesService();
 * // dependencies.ts: export const getShopwareServicesService = () => Shopware.Service(shopwareServicesService);
 */
export const rewriteService: UseRewrite = (use, context) => {
    const name = literalArgument(use.call);
    if (!use.call || !name || !isPath(use, 'Service') || !isIdentifierName(name)) {
        return null;
    }

    const accessor = `get${upperFirst(name)}`;
    const typing = context.index.typing('ServiceContainer', name);
    if (!typing || !context.isFree(accessor)) {
        return null;
    }

    return replaceNode(
        use.call,
        context,
        `${accessor}()`,
        [
            {
                kind: 'accessor',
                name: accessor,
                order: 0,
                body: `${accessorHead(typing, 'ServiceContainer', name)} => Shopware.Service(${name})`,
                imports: [{ module: SERVICE_NAMES, imported: name }],
            },
        ],
        serviceNameEdits(name, context.index.hasServiceName(name)),
    );
};
