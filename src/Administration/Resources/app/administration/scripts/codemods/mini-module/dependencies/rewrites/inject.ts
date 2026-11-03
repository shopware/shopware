/**
 * @sw-package framework
 * @private
 */

import ts from 'typescript';
import type { FileRewrite, Rewrite } from '../model';
import { isIdentifierName } from '../model';
import { replaceNode } from './helpers';
import { SERVICE_NAMES, serviceNameEdits } from './service';

/** The name dependencies.ts gives a service name constant, so `src/` reads it as a service: `acl` → `aclService`. */
export const serviceExportName = (name: string) => (name.endsWith('Service') ? name : `${name}Service`);

/**
 * The service names in `inject: ['a', 'b']` become constants from src/app/service-names, in the same order.
 * A name that is not a registered service, such as one a parent provides, stays for the sweep.
 * When the file declares the name already, `src/` imports it as `<name>Name`.
 *
 * @example
 * // inject: ['repositoryFactory', 'swProductDetailLoadAll']
 * // → inject: [repositoryFactoryService, 'swProductDetailLoadAll']
 * // dependencies.ts: export { repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
 */
export const rewriteInject: FileRewrite = (context) => {
    const rewrites: Rewrite[] = [];

    const visit = (node: ts.Node) => {
        if (
            ts.isPropertyAssignment(node) &&
            ts.isIdentifier(node.name) &&
            node.name.text === 'inject' &&
            ts.isArrayLiteralExpression(node.initializer)
        ) {
            node.initializer.elements.forEach((element) => {
                const name = ts.isStringLiteral(element) ? element.text : null;
                if (!name || !isIdentifierName(name) || !context.index.isService(name)) {
                    return;
                }

                const exported = serviceExportName(name);
                const local = [exported, `${exported}Name`].find((candidate) => context.isFree(candidate));
                if (!local) {
                    return;
                }

                const rewrite = replaceNode(
                    element,
                    context,
                    local,
                    [{ kind: 'reexport', module: SERVICE_NAMES, imported: name, exported }],
                    serviceNameEdits(name, context.index.hasServiceName(name)),
                );
                rewrites.push({ ...rewrite, imports: { [local]: exported } });
            });
        }

        node.forEachChild(visit);
    };

    visit(context.source);
    return rewrites;
};
