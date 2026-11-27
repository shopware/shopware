/**
 * @sw-package framework
 *
 * Follows the `v-if` chains of a template and says in which flag states each of their branches renders.
 */
import { NodeTypes } from '@vue/compiler-dom';
import type { ElementNode, TemplateChildNode } from '@vue/compiler-dom';
import { BOTH_STATES, inBothStates } from '../../model/flag-states';
import type { FlagContext, FlagStates } from '../../model/flag-states';
import { ConditionChain, expressionTruths } from '../flag-conditions';
import { chainMember, contentOf, endsChain, isWhitespace } from './template-nodes';
import type { ChainMember } from './template-nodes';

/**
 * @private
 *
 * What a walk over sibling nodes reports. `branch` holds the flag states in which the element renders as a member
 * of a `v-if` chain, `null` for an element without a condition.
 */
export interface BranchVisitor {
    comment: (content: string) => void;
    element: (element: ElementNode, branch: FlagStates | null) => void;
}

/**
 * @private
 *
 * Visits the comments and elements of `nodes` in their order. A chain continues over whitespace and comments
 * only, as in Vue.
 */
export function walkBranches(nodes: TemplateChildNode[], flags: FlagContext, visitor: BranchVisitor): void {
    let open: { member: ChainMember; chain: ConditionChain } | null = null;

    nodes.forEach((node) => {
        if (node.type === NodeTypes.COMMENT) {
            visitor.comment(node.content);

            return;
        }

        if (node.type !== NodeTypes.ELEMENT) {
            open = isWhitespace(node) ? open : null;

            return;
        }

        const member = chainMember(node, open?.member ?? null);

        if (member === null) {
            open = null;
            visitor.element(node, null);

            return;
        }

        const chain = member.continues && open !== null ? open.chain : new ConditionChain();

        open = endsChain(member) ? null : { member, chain };
        visitor.element(
            node,
            endsChain(member)
                ? chain.elseBranch()
                : chain.branch(expressionTruths(contentOf(member.condition.exp) ?? '', flags)),
        );
    });
}

/**
 * @private
 *
 * The branches around the current node, outermost first.
 */
export class BranchStack {
    private readonly branches: FlagStates[] = [];

    public get depth(): number {
        return this.branches.length;
    }

    /** Runs `visit` inside the branch, or just runs it for an element without a condition. */
    public enter(branch: FlagStates | null, visit: () => void): void {
        if (branch === null) {
            visit();

            return;
        }

        this.branches.push(branch);
        visit();
        this.branches.pop();
    }

    /** The flag states in which every branch that was entered at `depth` or deeper renders. */
    public within(depth = 0): FlagStates {
        return this.branches.slice(depth).reduce(inBothStates, BOTH_STATES);
    }
}
