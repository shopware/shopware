/**
 * @sw-package framework
 *
 * Builds one unit per registered service: the object behind the registration with its members, where it is
 * declared, and whether it is public.
 */
import ts from 'typescript';
import type { Marker } from '../../../../eslint-rules/deprecation-rules/utils/api-markers';
import { existingIn, mergeStates } from '../../model/flag-states';
import type { FlagContext } from '../../model/flag-states';
import type { ServiceUnit } from '../../model/model';
import type { SourceLine } from '../../model/source-line';
import type { SourceTree } from '../../source/source-tree';
import { Declarations } from '../declarations';
import type { Located } from '../declarations';
import { markersIn, Reading } from '../member-reading';
import { scriptLanguage, withoutScriptExtension } from '../modules';
import { declarationDoc, isPrivateDoc, lineOf, statementOf, withoutTypeSyntax } from '../script-nodes';
import { instanceMembers, objectMembers } from './object-members';
import type { ObjectMembers } from './object-members';
import { isSameObject } from './provided-object';
import type { ProvidedObject } from './provided-object';
import { serviceRegistrations } from './service-registry';
import type { ServiceRegistration } from './service-registry';

const SERVICE_CONTAINER = 'ServiceContainer';

// The alias the sources write for `any`.
const UNTYPED_ENTRY = /^(?:\$TSFixMe|any)$/;

function interfacesNamed(node: ts.Node, name: string): ts.InterfaceDeclaration[] {
    const found = ts.isInterfaceDeclaration(node) && node.name.text === name ? [node] : [];

    ts.forEachChild(node, (child) => {
        found.push(...interfacesNamed(child, name));
    });

    return found;
}

/** What the `ServiceContainer` interface, which any file may extend, says about each service name. */
function declaredServices(tree: SourceTree, declarations: Declarations): Map<string, ServiceUnit['declared']> {
    const entries = tree.files
        .filter((file) => file.endsWith('.ts') && tree.read(file).includes(`interface ${SERVICE_CONTAINER}`))
        .flatMap((file) => {
            const source = declarations.parse(file);

            return interfacesNamed(source, SERVICE_CONTAINER)
                .flatMap(({ members }) => members.filter(ts.isPropertySignature))
                .flatMap((entry): [string, ServiceUnit['declared']][] => {
                    const isNamed = ts.isIdentifier(entry.name) || ts.isStringLiteralLike(entry.name);
                    const type = entry.type?.getText(source) ?? 'any';

                    return isNamed ? [[entry.name.text, UNTYPED_ENTRY.test(type) ? 'any' : 'typed']] : [];
                });
        });

    return new Map(entries);
}

/** What the reader found behind a registration: the members, and the node that declares them. */
interface ServiceObject extends ObjectMembers {
    /** The class or object literal that holds the members, `null` when there is none to point to. */
    home: Located | null;
    /** The declaration whose comment is about the service as a whole: its class, or the factory that builds it. */
    holder: Located | null;
}

const NO_MEMBERS: ObjectMembers = { members: [], notes: [] };

function serviceObject(provided: ProvidedObject, declarations: Declarations): ServiceObject {
    switch (provided.kind) {
        case 'instance':
            return {
                ...instanceMembers(provided.declaration, declarations),
                home: provided.declaration,
                holder: provided.declaration,
            };
        case 'object':
            return {
                ...objectMembers(provided.declaration, declarations),
                home: provided.declaration,
                holder: provided.factory,
            };
        case 'callable':
            return { ...NO_MEMBERS, home: provided.declaration, holder: provided.declaration };
        case 'nothing':
            return { ...NO_MEMBERS, home: null, holder: null };
        case 'unresolved': {
            const reading = new Reading(provided.expression.source);

            reading.unresolved('provider', provided.expression.node);

            return { members: [], notes: reading.notes, home: null, holder: null };
        }
        default:
            return provided satisfies never;
    }
}

/** `export default ExampleService;` below the declaration. */
function defaultExportOf({ node, source }: Located): ts.Statement | undefined {
    const name = (node as { name?: ts.Node }).name;

    return name === undefined || !ts.isIdentifier(name)
        ? undefined
        : source.statements.filter(ts.isExportAssignment).find(({ expression }) => {
              const exported = withoutTypeSyntax(expression);

              return ts.isIdentifier(exported) && exported.text === name.text;
          });
}

/** What the comments about a service as a whole say. */
interface ServiceDoc {
    isPrivate: boolean;
    markers: Marker[];
}

/** The comment directly above the holder, and the one above its default export, where the sources often write it. */
function serviceDoc(holder: Located | null): ServiceDoc {
    const docs =
        holder === null
            ? []
            : [statementOf(holder.node), defaultExportOf(holder)]
                  .filter((statement) => statement !== undefined)
                  .map((statement) => declarationDoc(statement, holder.source));

    return { isPrivate: docs.some(isPrivateDoc), markers: docs.flatMap(markersIn) };
}

/** `src/core/service/api/cache.api.service.js` gives `core/service/api/cache.api.service`. */
function snapshotFile(source: string): string {
    return withoutScriptExtension(source).replace(/^src\//, '');
}

function serviceUnit(
    registration: ServiceRegistration,
    declared: ServiceUnit['declared'],
    declarations: Declarations,
): ServiceUnit {
    const { members, notes, home, holder } = serviceObject(registration.provided, declarations);
    const { isPrivate, markers } = serviceDoc(holder);
    const source = home?.source.fileName ?? registration.registeredIn;
    const at: SourceLine = home === null ? registration.at : lineOf(home.node, home.source);

    return {
        name: registration.name,
        registration: registration.registration,
        declared,
        language: scriptLanguage(source),
        visibility: isPrivate ? 'private' : 'public',
        visibilitySource: isPrivate ? 'export' : 'default',
        markers,
        registeredIn: registration.registeredIn,
        source,
        at,
        file: snapshotFile(source),
        states: registration.states,
        members: existingIn(registration.states, members),
        notes,
        sections: { method: at, property: at },
    };
}

/**
 * The container holds one service per name. Core registers some services twice with the same object, which is
 * one service. Two different objects under one name would hide each other, and the snapshot could list only one.
 */
function assertOneObjectPerName(registrations: ServiceRegistration[]): void {
    const first = new Map<string, ServiceRegistration>();

    registrations.forEach((registration) => {
        const known = first.get(registration.name);

        if (known !== undefined && !isSameObject(known.provided, registration.provided)) {
            throw new Error(
                `The service ${registration.name} is registered with two different objects, ` +
                    `in ${known.registeredIn} and in ${registration.registeredIn}.`,
            );
        }

        first.set(registration.name, known ?? registration);
    });
}

/**
 * @private
 *
 * The services of the tree, sorted by name.
 */
export function serviceUnits(tree: SourceTree, flags: FlagContext): ServiceUnit[] {
    const declarations = new Declarations(tree);
    const registrations = serviceRegistrations(tree, declarations, flags);
    const declared = declaredServices(tree, declarations);

    assertOneObjectPerName(registrations);

    return mergeStates(registrations, ({ name }) => name).map((registration) =>
        serviceUnit(registration, declared.get(registration.name) ?? 'untyped', declarations),
    );
}
