/**
 * @sw-package framework
 *
 * Builds the units of the global `Shopware` object: one for the object itself and one for every object below
 * it, such as `Shopware.Component` and `Shopware.Utils.string`. A unit lists the members of its object, so a
 * member that is gone is reported on the unit that held it.
 */
import ts from 'typescript';
import { byCodePoint } from '../../helpers/code-point-order';
import { BOTH_STATES } from '../../model/flag-states';
import type { Member } from '../../model/members';
import type { GlobalUnit } from '../../model/model';
import type { Declarations } from '../declarations';
import { distinctMarkers } from '../member-reading';
import { instanceMembers, objectMembers, staticMembers } from '../object-members';
import type { ObjectMembers } from '../object-members';
import { objectDoc, unitOf } from '../registered-units';
import type { UnitDoc, UnitParts } from '../registered-units';
import { memberOfObject, objectAt } from '../resolved-object';
import type { ResolvedObject } from '../resolved-object';
import { docOf, lineOf, memberName } from '../script-nodes';

const SHOPWARE = 'Shopware';

// `Shopware.Utils.string.camelCase` is as deep as plugin code reaches: the units end two objects below `Shopware`.
const MAX_DEPTH = 2;

const PUBLIC_TAG = /^[\s*]*@public\b/m;

/** An object with members of its own. A class is one that plugin code uses through its static members. */
type GlobalObject = Extract<ResolvedObject, { kind: GlobalUnit['shape'] }>;

function membersOf(object: GlobalObject, declarations: Declarations): ObjectMembers {
    switch (object.kind) {
        case 'instance':
            return instanceMembers(object.declaration, declarations);
        case 'object':
            return objectMembers(object.declaration, declarations);
        case 'class':
            return staticMembers(object.declaration, declarations);
        default:
            return object satisfies never;
    }
}

/**
 * An object below another one is a unit, also when it is empty, so the members it loses are reported. A
 * function is a method of its parent, and so is a class without static members, which plugin code only calls.
 */
function asGlobalObject(object: ResolvedObject | undefined, declarations: Declarations): GlobalObject | undefined {
    if (object === undefined || (object.kind !== 'instance' && object.kind !== 'object' && object.kind !== 'class')) {
        return undefined;
    }

    const isPlainClass = object.kind === 'class' && membersOf(object, declarations).members.length === 0;

    return isPlainClass ? undefined : object;
}

/**
 * The members of a private class that carry `@public`. Plugins cannot avoid them, so they are public API
 * although their class is not.
 */
function publicMembers({ declaration }: GlobalObject): Set<string> {
    const { node, source } = declaration;
    const names = ts.isClassLike(node)
        ? node.members.filter((member) => PUBLIC_TAG.test(docOf(member, source))).map((member) => memberName(member))
        : [];

    return new Set(names.filter((name) => name !== null));
}

/**
 * Why an object or a member is private: `export` by its own comment, `parent` because the object above it is.
 * `null` is public.
 */
type PrivateThrough = UnitParts['privateThrough'] | null;

/**
 * A unit as its parent sees it: where the parent holds it, and what the parent says about it. `privateThrough`
 * is `export` for a private member, which comments on the object it holds.
 */
interface Place {
    path: string;
    depth: number;
    privateThrough: PrivateThrough;
    markers: UnitDoc['markers'];
    heldBy: GlobalUnit['heldBy'];
    /** The file that builds the `Shopware` object, whose report holds every unit. */
    builtIn: string;
}

/**
 * What is public of an object: why the unit is private, and why a member is private although the unit is not.
 * `privateThrough` names the comment of the object itself before the one of the object above.
 */
interface Exposure {
    markers: UnitDoc['markers'];
    privateThrough: PrivateThrough;
    hides: (member: Member) => PrivateThrough;
}

/**
 * An object is private below a private object or member, and with a private class or declaration. In a private
 * class with `@public` members the object is public, and every other member stays private through that class.
 */
function exposureOf(place: Place, object: GlobalObject): Exposure {
    const own = objectDoc(object);
    const exposed = publicMembers(object);
    const privateThrough = own.isPrivate && exposed.size === 0 ? 'export' : place.privateThrough;

    return {
        markers: distinctMarkers([...place.markers, ...own.markers]),
        privateThrough,
        hides(member) {
            if (member.isPrivate) {
                return 'export';
            }

            return own.isPrivate && exposed.size > 0 && !exposed.has(member.name) ? 'parent' : null;
        },
    };
}

/** The units below an object by the member that holds each one, with the units below those behind it. */
function childrenOf(
    place: Place,
    object: GlobalObject,
    members: Member[],
    { privateThrough, hides }: Exposure,
    declarations: Declarations,
): Map<string, GlobalUnit[]> {
    if (place.depth >= MAX_DEPTH) {
        return new Map();
    }

    return new Map(
        members.flatMap((member): [string, GlobalUnit[]][] => {
            const child = asGlobalObject(memberOfObject(object, member.name, declarations), declarations);
            const below: Place = {
                ...place,
                path: `${place.path}.${member.name}`,
                depth: place.depth + 1,
                privateThrough: hides(member) ?? (privateThrough === null ? null : 'parent'),
                markers: member.markers,
                heldBy: { unit: place.path, kind: member.kind, name: member.name },
            };

            return child === undefined ? [] : [[member.name, unitsAt(below, child, declarations)]];
        }),
    );
}

/**
 * The unit of the object and the units below it. A member that holds an object private by its own comment is
 * private as a whole. One that holds an object private only through this one is not, so an object that becomes
 * private is one change, as one that is gone is.
 */
function unitsAt(place: Place, object: GlobalObject, declarations: Declarations): GlobalUnit[] {
    const read = membersOf(object, declarations);
    const exposure = exposureOf(place, object);
    const children = childrenOf(place, object, read.members, exposure, declarations);
    const isPrivate = (member: Member): boolean =>
        exposure.hides(member) !== null || children.get(member.name)?.[0].visibilitySource === 'export';
    const { node, source } = object.declaration;
    const unit = unitOf({
        name: place.path,
        states: BOTH_STATES,
        registeredIn: place.builtIn,
        reportedIn: place.builtIn,
        source: source.fileName,
        at: lineOf(node, source),
        members: read.members.map((member) => ({
            ...member,
            isPrivate: isPrivate(member),
        })),
        notes: read.notes,
        doc: { isPrivate: exposure.privateThrough !== null, markers: exposure.markers },
        privateThrough: exposure.privateThrough ?? 'export',
        sections: {},
    });

    return [{ ...unit, shape: object.kind, heldBy: place.heldBy }, ...[...children.values()].flat()];
}

/**
 * @private
 *
 * The units of the `Shopware` object, sorted by name. A tree without the file that builds the object has none.
 */
export function globalUnits(declarations: Declarations): GlobalUnit[] {
    const built = declarations.globalNamed(SHOPWARE);

    if (built === undefined) {
        return [];
    }

    const object = objectAt(built, declarations);

    if (object.kind !== 'instance') {
        throw new Error(
            `${built.source.fileName} no longer builds ${SHOPWARE} as an instance of a class, so the snapshot cannot read the object.`,
        );
    }

    const root: Place = {
        path: SHOPWARE,
        depth: 0,
        privateThrough: null,
        markers: [],
        heldBy: null,
        builtIn: object.declaration.source.fileName,
    };

    return unitsAt(root, object, declarations).sort((a, b) => byCodePoint(a.name, b.name));
}
