/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, PropReplacement, TemplateNode } from '../types';

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

// `backgroundStyle(stateType)` of sw-order-general-info reads the state of these computed entities
const GENERAL_INFO_ENTITIES: Record<string, string> = {
    order_transaction: 'transaction',
    order_delivery: 'delivery',
    order: 'order',
};

/**
 * The entity whose state a core helper call in `background-style` styles, or null: `backgroundStyle('order')` in an
 * override of sw-order-general-info, `stateSelectBackgroundStyle` in one of sw-order-details-state-card.
 */
function stateEntity(api: ComponentUsageRuleApi, attribute: TemplateNode): string | null {
    const expression = (
        api.ast.getDirectiveName(attribute) === 'bind' ? attribute.value?.expression : null
    ) as TemplateNode | null;

    if (expression?.type === 'CallExpression') {
        return generalInfoEntity(api, expression);
    }

    const isStateCardHelper = expression?.type === 'Identifier' && expression.name === 'stateSelectBackgroundStyle';

    return isStateCardHelper && api.getOverrideTargets().includes('sw-order-details-state-card') ? 'entity' : null;
}

function generalInfoEntity(api: ComponentUsageRuleApi, call: TemplateNode): string | null {
    const [argument] = call.arguments as TemplateNode[];
    const isHelper =
        call.callee.type === 'Identifier' && call.callee.name === 'backgroundStyle' && call.arguments.length === 1;

    if (!isHelper || argument.type !== 'Literal' || !Object.hasOwn(GENERAL_INFO_ENTITIES, argument.value)) {
        return null;
    }

    return api.getOverrideTargets().includes('sw-order-general-info')
        ? GENERAL_INFO_ENTITIES[argument.value as string]
        : null;
}

/**
 * Rewrites the `background-style` prop of the order state select into `state-name`, from which the select takes its
 * color since 6.7.16.0. Only the core helpers tell the state; with `state-name` set, the prop is removed.
 *
 * @example
 * replaceProp({ prop: 'background-style', with: backgroundStyleToStateName });
 * // :background-style="backgroundStyle('order')" in sw-order-general-info → :state-name="order.stateMachineState.technicalName"
 */
export const backgroundStyleToStateName: PropReplacement = {
    objectVBindReason: 'Object v-bind can hide state-name. Review the bound object and remove background-style manually.',
    manualReason(api, attribute) {
        if (api.ast.hasMatchingPropAttribute(api.node, 'state-name') || stateEntity(api, attribute)) {
            return null;
        }

        return 'Pass the technical name of the current state as state-name instead, e.g. :state-name="order.stateMachineState.technicalName".';
    },
    fix(api, attribute, fixer) {
        if (api.ast.hasMatchingPropAttribute(api.node, 'state-name')) {
            return api.removeAttribute(fixer, attribute);
        }

        return fixer.replaceText(
            attribute,
            `:state-name="${stateEntity(api, attribute) ?? ''}.stateMachineState.technicalName"`,
        );
    },
};
