/**
 * @sw-package framework
 *
 * What the readers of registered units share: finding the registrations, and the unit that a registration and
 * the object behind it give, with its files, its visibility and its markers.
 */
import ts from 'typescript';
import type { Marker } from '../../../eslint-rules/deprecation-rules/utils/api-markers';
import { existingIn } from '../model/flag-states';
import type { FlagContext, FlagStates } from '../model/flag-states';
import { DECLARED_KINDS } from '../model/members';
import type { Member, UnitMembers } from '../model/members';
import type { RegisteredUnit } from '../model/model';
import type { SourceLine } from '../model/source-line';
import type { SourceTree } from '../source/source-tree';
import type { Declarations, Located } from './declarations';
import { guardStates } from './flag-conditions';
import { markersIn } from './member-reading';
import { isScriptFile, scriptLanguage, withoutScriptExtension } from './modules';
import { collect, declarationDoc, isPrivateDoc, lineOf, statementOf, withoutTypeSyntax } from './script-nodes';
import type { MethodCall } from './script-nodes';

/**
 * @private
 *
 * A registration under a literal name. `at` is the line of the registration.
 */
export interface Registered {
    name: string;
    registeredIn: string;
    at: SourceLine;
    states: FlagStates;
}

/**
 * @private
 *
 * How the sources register the units of one kind.
 *
 * - `mention`: only saves parsing the files without a registration. `isCall` decides what counts as one.
 * - `read`: the name of the unit with what the call registers, `undefined` when the call names none as a literal.
 * - `forwardingFiles`: the files of core that pass a unit on under a name they get at runtime.
 * - `needs`: what a registration has to have, for the error about one that lacks it.
 */
export interface RegistrationCalls<Registration extends { name: string }> {
    mention: RegExp;
    isCall: (node: ts.Node) => node is MethodCall;
    read: (call: MethodCall, source: ts.SourceFile) => Registration | undefined;
    forwardingFiles: readonly string[];
    needs: string;
}

/**
 * @private
 *
 * Every registration of the kind in the tree, in the order of the files and of the calls in them.
 */
export function findRegistrations<Registration extends { name: string }>(
    calls: RegistrationCalls<Registration>,
    tree: SourceTree,
    declarations: Declarations,
    flags: FlagContext,
): (Registration & Registered)[] {
    return tree.files
        .filter((file) => isScriptFile(file) && calls.mention.test(tree.read(file)))
        .flatMap((file) => {
            const source = declarations.parse(file);

            return collect(source, calls.isCall).flatMap((call) => {
                const registration = calls.read(call, source);

                if (registration !== undefined) {
                    return [
                        {
                            ...registration,
                            registeredIn: file,
                            at: lineOf(call.expression.name, source),
                            states: guardStates(call, flags),
                        },
                    ];
                }

                if (calls.forwardingFiles.includes(file)) {
                    return [];
                }

                throw new Error(`${file}:${lineOf(call, source).line}: ${calls.needs}, so the snapshot can name it.`);
            });
        });
}

/**
 * @private
 *
 * What a reader found behind a registration.
 *
 * - `home`: the class, object literal or function that holds the members, `null` when there is none to point to.
 * - `holder`: the declaration whose comment is about the unit as a whole, such as its class or the constant
 *   that holds a store.
 * - `sections`: the lines of the places that hold the members of a kind, where they differ from `home`.
 */
export interface RegisteredObject {
    members: Member[];
    notes: string[];
    home: Located | null;
    holder: Located | null;
    sections?: UnitMembers['sections'];
}

/** `export default exampleStore;` below the declaration. */
function defaultExportOf({ node, source }: Located): ts.Statement | undefined {
    const name = (node as { name?: ts.Node }).name;

    return name === undefined || !ts.isIdentifier(name)
        ? undefined
        : source.statements.filter(ts.isExportAssignment).find(({ expression }) => {
              const exported = withoutTypeSyntax(expression);

              return ts.isIdentifier(exported) && exported.text === name.text;
          });
}

/** What the comments about a unit as a whole say. */
interface UnitDoc {
    isPrivate: boolean;
    markers: Marker[];
}

/** The comment directly above the holder, and the one above its default export, where the sources often write it. */
function unitDoc(holder: Located | null): UnitDoc {
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

/**
 * @private
 *
 * The unit of a registration with the object behind it. An object without a home has the registration as its
 * source. The members exist in the flag states of the registration at most.
 */
export function registeredUnit(
    registration: Registered,
    { members, notes, home, holder, sections }: RegisteredObject,
): RegisteredUnit {
    const { isPrivate, markers } = unitDoc(holder);
    const source = home?.source.fileName ?? registration.registeredIn;
    const at: SourceLine = home === null ? registration.at : lineOf(home.node, home.source);

    return {
        name: registration.name,
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
        sections: { ...Object.fromEntries(DECLARED_KINDS.map((kind) => [kind, at])), ...sections },
    };
}
