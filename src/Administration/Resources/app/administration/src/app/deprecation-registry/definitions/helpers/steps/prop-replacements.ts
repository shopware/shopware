/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { PropReplacement } from '../types';
import {} from '../shared';

/**
 * Rewrites the `router-link` prop of a button into a `@click` handler that pushes the same route.
 *
 * @example
 * replaceProp({ prop: 'router-link', with: routerLinkClickHandler, fix: 'unsafe-auto' });
 * // router-link="sw.dashboard.index" → @click="$router.push('sw.dashboard.index')"
 */
export const routerLinkClickHandler: PropReplacement = {
    objectVBindReason: 'Object v-bind can hide router-link usage. Review the bound object and migrate navigation manually.',
    produces: { kind: 'event', name: 'click' },
    fix(api, attribute, fixer) {
        const source = api.ast.getAttributeValueSource(attribute);
        const route = api.ast.getDirectiveName(attribute) === 'bind' ? source : `'${source}'`;

        return fixer.replaceText(attribute, `@click="$router.push(${route})"`);
    },
};

/**
 * Rewrites the `ai-badge` prop of a card into an AI badge rendered in the card's title slot.
 *
 * @example
 * replaceProp({ prop: 'ai-badge', with: aiBadgeTitleSlot, fix: 'unsafe-auto' });
 * // :ai-badge="isAi" → <slot name="title"><sw-ai-copilot-badge v-if="isAi" /></slot>
 */
export const aiBadgeTitleSlot: PropReplacement = {
    objectVBindReason:
        'Object v-bind can hide ai-badge usage. Review the bound object and add the title slot manually if needed.',
    fix(api, attribute, fixer) {
        const indentation = ' '.repeat((api.node.startTag.loc.start.column as number) + 4);
        const condition =
            api.ast.getDirectiveName(attribute) === 'bind' ? ` v-if="${api.ast.getAttributeValueSource(attribute)}"` : '';

        return [
            api.removeAttribute(fixer, attribute),
            fixer.insertTextAfter(
                api.node.startTag,
                `\n${indentation}<slot name="title"><sw-ai-copilot-badge${condition} /></slot>\n${indentation}`,
            ),
        ];
    },
};
