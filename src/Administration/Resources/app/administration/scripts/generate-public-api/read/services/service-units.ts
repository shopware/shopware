/**
 * @sw-package framework
 *
 * Builds one unit per registered service: the object behind the registration with its members, where it is
 * declared, and whether it is public.
 */
import { mergeStates } from '../../model/flag-states';
import type { FlagContext } from '../../model/flag-states';
import type { ServiceUnit } from '../../model/model';
import type { SourceTree } from '../../source/source-tree';
import { objectDoc, registeredUnit } from '../registered-units';
import type { RegisteredObject } from '../registered-units';
import type { Declarations } from '../declarations';
import { interfaceEntries } from '../global-interfaces';
import { Reading } from '../member-reading';
import { instanceMembers, objectMembers } from '../object-members';
import { isSameObject } from '../resolved-object';
import type { ResolvedObject } from '../resolved-object';
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

const NO_MEMBERS = { members: [], notes: [] };

function membersOf(
    provided: ResolvedObject,
    declarations: Declarations,
): Pick<RegisteredObject, 'members' | 'notes' | 'home'> {
    switch (provided.kind) {
        case 'instance':
            return { ...instanceMembers(provided.declaration, declarations), home: provided.declaration };
        case 'object':
            return { ...objectMembers(provided.declaration, declarations), home: provided.declaration };
        case 'class':
        case 'function':
            return { ...NO_MEMBERS, home: provided.declaration };
        case 'nothing':
            return { ...NO_MEMBERS, home: null };
        case 'unresolved': {
            const reading = new Reading(provided.expression.source);

            reading.unresolved('provider', provided.expression.node);

            return { members: [], notes: reading.notes, home: null };
        }
        default:
            return provided satisfies never;
    }
}

function serviceObject(provided: ResolvedObject, declarations: Declarations): RegisteredObject {
    return { ...membersOf(provided, declarations), sections: {}, doc: objectDoc(provided) };
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
export function serviceUnits(tree: SourceTree, declarations: Declarations, flags: FlagContext): ServiceUnit[] {
    const registrations = serviceRegistrations(tree, declarations, flags);
    const declared = interfaceEntries(SERVICE_CONTAINER, tree, declarations);

    assertOneObjectPerName(registrations);

    return mergeStates(registrations, ({ name }) => name).map((registration) => ({
        ...registeredUnit(registration, serviceObject(registration.provided, declarations)),
        registration: registration.registration,
        declared: declaredAs(declared.get(registration.name)),
    }));
}
