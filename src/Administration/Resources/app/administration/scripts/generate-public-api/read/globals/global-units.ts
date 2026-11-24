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
import type { SourceLine } from '../../model/source-line';
import type { Declarations } from '../declarations';
import { markersIn } from '../member-reading';
import { instanceMembers, objectMembers, staticMembers } from '../object-members';
import type { ObjectMembers } from '../object-members';
import { registeredUnit } from '../registered-units';
import type { RegisteredObject, UnitDoc } from '../registered-units';
import { memberOfObject, objectAt } from '../resolved-object';
import type { ResolvedObject } from '../resolved-object';
import { declarationDocs, docOf, isPrivateDoc, lineOf, memberName } from '../script-nodes';

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

/** A unit as its parent sees it: where the parent holds it, and what the parent says about it. */
interface Place {
    path: string;
    at: SourceLine;
    depth: number;
    isPrivate: boolean;
    markers: UnitDoc['markers'];
    /** The file that builds the `Shopware` object, whose report holds every unit. */
    builtIn: string;
}

/** What is public of an object: whether the unit is, and which of its members are hidden although it is. */
interface Exposure {
    doc: UnitDoc;
    privateThrough: RegisteredObject['privateThrough'];
    hides: (member: Member) => boolean;
}

/**
 * An object is private below a private member, and with a private class or declaration. In a private class
 * with `@public` members the object is public, and every other member stays private.
 */
function exposureOf(place: Place, object: GlobalObject): Exposure {
    const docs = declarationDocs(object.declaration.node, object.declaration.source);
    const exposed = publicMembers(object);
    const isPrivateHolder = docs.some(isPrivateDoc);

    return {
        doc: {
            isPrivate: place.isPrivate || (isPrivateHolder && exposed.size === 0),
            markers: [...place.markers, ...docs.flatMap(markersIn)],
        },
        privateThrough: place.isPrivate ? 'parent' : 'export',
        hides: (member) => member.isPrivate || (isPrivateHolder && exposed.size > 0 && !exposed.has(member.name)),
    };
}

/** The units below an object by the member that holds each one, with the units below those behind it. */
function childrenOf(
    place: Place,
    object: GlobalObject,
    members: Member[],
    { doc, hides }: Exposure,
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
                at: member.at,
                depth: place.depth + 1,
                isPrivate: doc.isPrivate || hides(member),
                markers: member.markers,
            };

            return child === undefined ? [] : [[member.name, unitsAt(below, child, declarations)]];
        }),
    );
}

/** The unit of the object and the units below it. A member that holds a private object is private as a whole. */
function unitsAt(place: Place, object: GlobalObject, declarations: Declarations): GlobalUnit[] {
    const read = membersOf(object, declarations);
    const exposure = exposureOf(place, object);
    const children = childrenOf(place, object, read.members, exposure, declarations);
    const holdsPrivate = ({ name }: Member): boolean => children.get(name)?.[0].visibility === 'private';
    const unit = registeredUnit(
        { name: place.path, registeredIn: place.builtIn, at: place.at, states: BOTH_STATES },
        {
            members: read.members.map((member) => ({
                ...member,
                isPrivate: exposure.hides(member) || holdsPrivate(member),
            })),
            notes: read.notes,
            home: object.declaration,
            doc: exposure.doc,
            privateThrough: exposure.privateThrough,
            reportedIn: place.builtIn,
        },
    );

    return [{ ...unit, shape: object.kind }, ...[...children.values()].flat()];
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

    const { node, source } = object.declaration;
    const root: Place = {
        path: SHOPWARE,
        at: lineOf(node, source),
        depth: 0,
        isPrivate: false,
        markers: [],
        builtIn: source.fileName,
    };

    return unitsAt(root, object, declarations).sort((a, b) => byCodePoint(a.name, b.name));
}
