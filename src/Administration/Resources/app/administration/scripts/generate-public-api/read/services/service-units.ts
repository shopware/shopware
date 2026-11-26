/**
 * @sw-package framework
 *
 * Builds one unit per registered service: the object behind the registration with its members, where it is
 * declared, and whether it is public.
 */
import type ts from 'typescript';
import { byCodePoint } from '../../helpers/code-point-order';
import { existingIn, mergeStates, perState } from '../../model/flag-states';
import type { FlagContext } from '../../model/flag-states';
import type { Model, ServiceClass, ServiceUnit } from '../../model/model';
import type { SourceTree } from '../../source/source-tree';
import { registeredUnit } from '../registered-units';
import type { RegisteredObject } from '../registered-units';
import type { Declarations } from '../declarations';
import { interfaceEntries } from '../global-interfaces';
import { Reading } from '../member-reading';
import { lineOf } from '../script-nodes';
import { classChain, objectMembers } from './object-members';
import type { ClassMembers } from './object-members';
import { isSameObject } from './provided-object';
import type { ProvidedObject } from './provided-object';
import { serviceRegistrations } from './service-registry';
import type { ServiceRegistration } from './service-registry';

const SERVICE_CONTAINER = 'ServiceContainer';

// The alias the sources write for `any`.
const UNTYPED_ENTRY = /^(?:\$TSFixMe|any)$/;

/** What `ServiceContainer` says about a service name, given the type of its entry. */
function declaredAs(type: string | undefined): ServiceUnit['declared'] {
    if (type === undefined) {
        return 'untyped';
    }

    return UNTYPED_ENTRY.test(type) ? 'any' : 'typed';
}

/** What the reader found behind a registration, with the class of an instance and the classes it extends. */
interface ServiceObject extends RegisteredObject {
    classes: ClassMembers[];
}

const NO_MEMBERS: Pick<ServiceObject, 'members' | 'notes' | 'classes'> = { members: [], notes: [], classes: [] };

function serviceObject(provided: ProvidedObject, declarations: Declarations): ServiceObject {
    switch (provided.kind) {
        case 'instance': {
            const classes = classChain(provided.declaration, declarations);
            const [{ members, notes }] = classes;

            return { members, notes, classes, home: provided.declaration, holder: provided.declaration };
        }
        case 'object':
            return {
                ...objectMembers(provided.declaration, declarations),
                classes: [],
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

            return { ...NO_MEMBERS, notes: reading.notes, home: null, holder: null };
        }
        default:
            return provided satisfies never;
    }
}

/** A service as the reader finds it, before the classes of all services are known. */
interface ReadService {
    unit: Omit<ServiceUnit, 'class'>;
    chain: ClassMembers[];
}

interface NamedClass {
    name: string;
    read: ClassMembers;
}

/**
 * The classes of a chain that a finding can name, nearest first. A finding names a class by its name, so an
 * anonymous class is left out, and so are two classes of one name.
 */
function namedChains(services: ReadService[]): (chain: ClassMembers[]) => NamedClass[] {
    const declarationsByName = new Map<string, Set<ts.Node>>();
    const named = (chain: ClassMembers[]): NamedClass[] =>
        chain.flatMap((read) => {
            const name = read.declaration.node.name?.text;

            return name === undefined ? [] : [{ name, read }];
        });

    named(services.flatMap(({ chain }) => chain)).forEach(({ name, read }) => {
        declarationsByName.set(name, (declarationsByName.get(name) ?? new Set()).add(read.declaration.node));
    });

    return (chain) => named(chain).filter(({ name }) => declarationsByName.get(name)?.size === 1);
}

interface ClassOfServices extends NamedClass {
    parent: NamedClass | undefined;
    instances: ServiceUnit[];
}

/** A class is public when one of its services is, and it exists in the flag states of its services. */
function serviceClass({ name, read, parent, instances }: ClassOfServices): ServiceClass {
    const { declaration, members, notes } = read;
    const states = perState((state) => instances.some((service) => service.states[state]));
    const at = lineOf(declaration.node, declaration.source);

    return {
        name,
        extends: parent === undefined ? null : parent.name,
        visibility: instances.some(({ visibility }) => visibility === 'public') ? 'public' : 'private',
        source: declaration.source.fileName,
        at,
        states,
        members: existingIn(states, members),
        notes,
        sections: { method: at, property: at },
    };
}

/** Each service with the nearest class a finding can name, and those classes, each linked to the one it extends. */
function withClasses(read: ReadService[]): Pick<Model, 'services' | 'serviceClasses'> {
    const namedChain = namedChains(read);
    const byDeclaration = new Map<ts.Node, ClassOfServices>();
    const services = read.map(({ unit, chain }) => {
        const classes = namedChain(chain);
        const service: ServiceUnit = { ...unit, class: classes.length === 0 ? null : classes[0].name };

        classes.forEach((named, index) => {
            const known = byDeclaration.get(named.read.declaration.node);

            byDeclaration.set(named.read.declaration.node, {
                ...named,
                parent: classes[index + 1],
                instances: [...(known?.instances ?? []), service],
            });
        });

        return service;
    });

    return {
        services,
        serviceClasses: [...byDeclaration.values()].map(serviceClass).sort((a, b) => byCodePoint(a.name, b.name)),
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
 * The services of the tree, sorted by name, and the classes they are instances of.
 */
export function serviceUnits(
    tree: SourceTree,
    declarations: Declarations,
    flags: FlagContext,
): Pick<Model, 'services' | 'serviceClasses'> {
    const registrations = serviceRegistrations(tree, declarations, flags);
    const declared = interfaceEntries(SERVICE_CONTAINER, tree, declarations);

    assertOneObjectPerName(registrations);

    return withClasses(
        mergeStates(registrations, ({ name }) => name).map((registration) => {
            const object = serviceObject(registration.provided, declarations);

            return {
                unit: {
                    ...registeredUnit(registration, object),
                    registration: registration.registration,
                    declared: declaredAs(declared.get(registration.name)),
                },
                chain: object.classes,
            };
        }),
    );
}
