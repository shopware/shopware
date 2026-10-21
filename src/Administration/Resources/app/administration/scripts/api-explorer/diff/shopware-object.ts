/**
 * @sw-package framework
 */

import type { ShopwareObjectNode, ShopwareObjectNodeKind, SnapshotDiagnostic } from '../snapshot/types';
import {
    addedChange,
    deprecationFindings,
    isPublic,
    normalizeText,
    removedChange,
    resolveChange,
    sameDeprecation,
    textChanged,
    type ChangeCollector,
    type ChangeTarget,
    type DeprecatedOwner,
    type Finding,
} from './changes';
import { compareCallSignatures } from './signatures';

const CONTAINER_KINDS: ShopwareObjectNodeKind[] = [
    'object',
    'class',
    'instance',
];

interface NodePair {
    base: ShopwareObjectNode;
    head: ShopwareObjectNode;
}

export interface ObjectDiffContext {
    collector: ChangeCollector;
    baseDiagnostics: SnapshotDiagnostic[];
    headDiagnostics: SnapshotDiagnostic[];
}

function nodeTarget(
    owner: string,
    base: ShopwareObjectNode | undefined,
    head: ShopwareObjectNode | undefined,
): ChangeTarget {
    return {
        area: 'shopwareObject',
        kind: 'objectMember',
        owner,
        member: (head ?? base)?.path ?? '',
        before: base ? normalizeText(base.signature) : null,
        after: head ? normalizeText(head.signature) : null,
        location: head?.location ?? base?.location ?? null,
    };
}

function compareNodes({ base, head }: NodePair): Finding[] {
    if (base.kind !== head.kind) {
        return [
            {
                severity: 'breaking',
                change: 'changed',
                reason: `Kind changed from ${base.kind} to ${head.kind}.`,
                before: `${base.kind}: ${normalizeText(base.signature)}`,
                after: `${head.kind}: ${normalizeText(head.signature)}`,
            },
        ];
    }

    if (base.callSignatures.length > 0 || head.callSignatures.length > 0) {
        return compareCallSignatures(base.callSignatures, head.callSignatures);
    }

    if (!CONTAINER_KINDS.includes(head.kind) && textChanged(base.signature, head.signature)) {
        return [{ severity: 'potentiallyBreaking', change: 'changed', reason: 'Type changed.' }];
    }

    return [];
}

/** Visibility and deprecation are inherited from ancestors, so a change is only reported where it starts. */
function diffNode(
    pair: NodePair,
    parent: NodePair | null,
    deprecatedAncestor: DeprecatedOwner | null,
    owner: string,
    context: ObjectDiffContext,
): void {
    const { base, head } = pair;
    const inheritsDeprecation =
        parent !== null &&
        sameDeprecation(parent.base.deprecation, base.deprecation) &&
        sameDeprecation(parent.head.deprecation, head.deprecation);
    const parentVisibilityChanged = parent !== null && isPublic(parent.base.visibility) !== isPublic(parent.head.visibility);

    context.collector.add(
        resolveChange(
            nodeTarget(owner, base, head),
            [
                ...compareNodes(pair),
                ...(inheritsDeprecation ? [] : deprecationFindings(base.deprecation, head.deprecation)),
            ],
            {
                base: base.visibility,
                head: head.visibility,
                reportTransition: !parentVisibilityChanged && isPublic(base.visibility) !== isPublic(head.visibility),
            },
        ),
    );

    if (base.truncated !== head.truncated) {
        (base.truncated ? context.baseDiagnostics : context.headDiagnostics).push({
            file: null,
            message: `The walk was truncated at ${base.path}; its members were not compared.`,
        });
        return;
    }

    const childAncestor = base.deprecation
        ? { label: `\`${base.path}\``, deprecation: base.deprecation }
        : deprecatedAncestor;
    diffChildren(base.children, head.children, pair, childAncestor, base.path, context);
}

function diffChildren(
    baseChildren: ShopwareObjectNode[],
    headChildren: ShopwareObjectNode[],
    parent: NodePair | null,
    deprecatedAncestor: DeprecatedOwner | null,
    owner: string,
    context: ObjectDiffContext,
): void {
    const headByPath = new Map(
        headChildren.map((node) => [
            node.path,
            node,
        ]),
    );
    const basePaths = new Set(baseChildren.map((node) => node.path));

    baseChildren.forEach((base) => {
        const head = headByPath.get(base.path);

        if (head) {
            diffNode({ base, head }, parent, deprecatedAncestor, owner, context);
        } else {
            context.collector.add(
                removedChange(nodeTarget(owner, base, undefined), base.visibility, base.deprecation, deprecatedAncestor),
            );
        }
    });

    headChildren
        .filter((head) => !basePaths.has(head.path))
        .forEach((head) => {
            context.collector.add(addedChange(nodeTarget(owner, undefined, head), head.visibility));
        });
}

export function diffShopwareObject(base: ShopwareObjectNode, head: ShopwareObjectNode, context: ObjectDiffContext): void {
    diffChildren([base], [head], null, null, '', context);
}
