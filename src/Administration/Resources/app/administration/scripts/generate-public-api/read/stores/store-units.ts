/**
 * @sw-package framework
 *
 * Builds one unit per registered store: its definition with the state, getters and actions, where it is
 * declared, and whether it is public.
 */
import ts from 'typescript';
import { byCodePoint } from '../../helpers/code-point-order';
import type { FlagContext } from '../../model/flag-states';
import type { StoreUnit } from '../../model/model';
import type { SourceTree } from '../../source/source-tree';
import { registeredUnit, unitDoc } from '../registered-units';
import type { RegisteredObject } from '../registered-units';
import type { Declarations } from '../declarations';
import { interfaceEntries } from '../global-interfaces';
import { Reading } from '../member-reading';
import { isFunctionNode } from '../script-nodes';
import { optionsMembers, setupMembers } from './store-members';
import { storeRegistrations } from './store-registry';
import type { StoreRegistration } from './store-registry';

const ROOT_STATE = 'PiniaRootState';

/** The members behind a registration, with the style its definition has. One the reader cannot follow is noted. */
function storeObject(
    { definition, holder }: StoreRegistration,
    declarations: Declarations,
): RegisteredObject & Pick<StoreUnit, 'style'> {
    const { node, source } = definition;

    if (ts.isObjectLiteralExpression(node)) {
        return {
            ...optionsMembers({ node, source }, declarations),
            style: 'options',
            home: definition,
            doc: unitDoc(holder),
        };
    }

    if (isFunctionNode(node)) {
        return { ...setupMembers({ node, source }, declarations), style: 'setup', home: definition, doc: unitDoc(holder) };
    }

    const reading = new Reading(source);

    reading.unresolved('store', node);

    return { members: [], notes: reading.notes, sections: {}, style: 'unknown', home: null, doc: unitDoc(holder) };
}

/** A second registration of an id would replace the first, and the snapshot could list only one of them. */
function assertUniqueIds(registrations: StoreRegistration[]): void {
    const registeredIn = new Map<string, string>();

    registrations.forEach(({ name, registeredIn: file }) => {
        const first = registeredIn.get(name);

        if (first !== undefined) {
            throw new Error(`The store ${name} is registered twice, in ${first} and in ${file}.`);
        }

        registeredIn.set(name, file);
    });
}

/**
 * @private
 *
 * The stores of the tree, sorted by name.
 */
export function storeUnits(tree: SourceTree, declarations: Declarations, flags: FlagContext): StoreUnit[] {
    const registrations = storeRegistrations(tree, declarations, flags);
    const declared = interfaceEntries(ROOT_STATE, tree, declarations);

    assertUniqueIds(registrations);

    return registrations
        .map((registration): StoreUnit => {
            const { style, ...object } = storeObject(registration, declarations);

            return {
                ...registeredUnit(registration, object),
                style,
                declared: declared.has(registration.name) ? 'typed' : 'untyped',
            };
        })
        .sort((a, b) => byCodePoint(a.name, b.name));
}
