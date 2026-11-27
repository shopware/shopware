/**
 * @sw-package framework
 *
 * Finds the services core registers: the calls that name a service and its provider, and the API services,
 * which register under the name their constructor sets.
 */
import ts from 'typescript';
import type { FlagContext } from '../../model/flag-states';
import { BOTH_STATES } from '../../model/flag-states';
import type { ServiceUnit } from '../../model/model';
import type { SourceTree } from '../../source/source-tree';
import type { Declarations } from '../declarations';
import { isScriptFile } from '../modules';
import { findRegistrations } from '../registered-units';
import type { Registered } from '../registered-units';
import { isMethodCall, lineOf } from '../script-nodes';
import type { MethodCall } from '../script-nodes';
import { thisAssignments } from './object-members';
import { providedBy } from './provided-object';
import type { ProvidedObject } from './provided-object';

/**
 * @private
 *
 * One registered service with the object behind it.
 */
export interface ServiceRegistration extends Registered {
    registration: ServiceUnit['registration'];
    provided: ProvidedObject;
}

const SERVICE_ACCESSOR = /(?:^|\.)Service$/;

/** `<application>.addServiceProvider(...)` or `Service().register(...)`. */
function isProviderCall(node: ts.Node): node is MethodCall {
    if (!isMethodCall(node)) {
        return false;
    }

    const { name, expression: receiver } = node.expression;

    if (name.text === 'addServiceProvider') {
        return true;
    }

    return (
        name.text === 'register' &&
        ts.isCallExpression(receiver) &&
        receiver.arguments.length === 0 &&
        SERVICE_ACCESSOR.test(receiver.expression.getText())
    );
}

function providers(tree: SourceTree, declarations: Declarations, flags: FlagContext): ServiceRegistration[] {
    return findRegistrations(
        {
            mention: /\baddServiceProvider\s*\(|\bService\s*\(\s*\)\s*\.\s*register\s*\(/,
            isCall: isProviderCall,
            read: ({ arguments: [name, provider] }, source) =>
                name !== undefined && provider !== undefined && ts.isStringLiteralLike(name)
                    ? {
                          name: name.text,
                          registration: 'provider' as const,
                          provided: providedBy(provider, source, declarations),
                      }
                    : undefined,
            /*
             * The first registers every API service, which `apiServices` reads below, and the second is
             * `Shopware.Service().register` itself.
             */
            forwardingFiles: ['src/app/init-pre/api-services.init.ts', 'src/core/factory/service.factory.ts'],
            needs: 'a service needs a literal name and a provider',
        },
        tree,
        declarations,
        flags,
    );
}

const API_SERVICE_FOLDER = 'src/core/service/api/';

const API_SERVICE_INDEX = `${API_SERVICE_FOLDER}index.ts`;

// What `index.ts` hands to `import.meta.glob`: every script below its folder, which `apiServices` relies on.
const API_SERVICE_GLOB = './**/!(*.spec).{j,t}s';

function globPattern(node: ts.Node): string | undefined {
    const isGlob = ts.isCallExpression(node) && node.expression.getText().replace(/\s/g, '') === 'import.meta.glob';

    if (!isGlob) {
        return ts.forEachChild(node, globPattern);
    }

    const [pattern] = node.arguments;

    return pattern !== undefined && ts.isStringLiteralLike(pattern) ? pattern.text : '';
}

/**
 * The boot sequence creates one instance of the default export of every file below the API service folder and
 * registers it under `instance.name`.
 */
function apiServices(tree: SourceTree, declarations: Declarations): ServiceRegistration[] {
    if (!tree.has(API_SERVICE_INDEX)) {
        return [];
    }

    if (globPattern(declarations.parse(API_SERVICE_INDEX)) !== API_SERVICE_GLOB) {
        throw new Error(
            `${API_SERVICE_INDEX} no longer loads the API services with import.meta.glob('${API_SERVICE_GLOB}'), ` +
                'so the snapshot does not know which files hold one.',
        );
    }

    return tree.files
        .filter((file) => file.startsWith(API_SERVICE_FOLDER) && isScriptFile(file) && file !== API_SERVICE_INDEX)
        .map((file) => {
            const exported = declarations.exported(file, 'default');
            const declaration = exported !== undefined && ts.isClassLike(exported.node) ? exported.node : undefined;
            const name = declaration && thisAssignments(declaration).find((assignment) => assignment.name === 'name');

            if (
                exported === undefined ||
                declaration === undefined ||
                name === undefined ||
                !ts.isStringLiteralLike(name.value)
            ) {
                throw new Error(
                    `${file}: an API service has to export a class by default whose constructor sets this.name to a literal, ` +
                        'so the snapshot can name it.',
                );
            }

            return {
                name: name.value.text,
                registration: 'api-service',
                registeredIn: API_SERVICE_INDEX,
                at: lineOf(name.statement, exported.source),
                states: BOTH_STATES,
                provided: { kind: 'instance', declaration: { node: declaration, source: exported.source } },
            };
        });
}

/**
 * @private
 *
 * Every service registration of the tree.
 */
export function serviceRegistrations(
    tree: SourceTree,
    declarations: Declarations,
    flags: FlagContext,
): ServiceRegistration[] {
    return [...apiServices(tree, declarations), ...providers(tree, declarations, flags)];
}
