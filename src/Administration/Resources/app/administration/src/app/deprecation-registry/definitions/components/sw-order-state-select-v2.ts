/**
 * @sw-package framework
 */

import { componentMigration, removeProp, replaceProp, upgradeGuide } from '../helpers';
import type { PropReplacement } from '../helpers';

/** The `background-style` bindings of core templates, and the state each of them stood for. */
const CORE_BACKGROUND_STYLE_STATES: Record<string, string> = {
    "backgroundStyle('order')": 'order.stateMachineState.technicalName',
    "backgroundStyle('order_transaction')": 'transaction.stateMachineState.technicalName',
    "backgroundStyle('order_delivery')": 'delivery.stateMachineState.technicalName',
    stateSelectBackgroundStyle: 'entity.stateMachineState.technicalName',
};

// The placeholder core passed: the translated name of the current state
const TRANSLATED_STATE_NAME = /^([\w$.]+)\.stateMachineState\.translated\.name$/;

/**
 * Replaces `background-style` with `:state-name`, the technical name of the state whose translated name
 * is the placeholder, or of the state a core `background-style` binding stood for. Without either, or
 * when the element already sets `state-name`, the prop is only removed.
 *
 * @example
 * replaceProp({ prop: 'background-style', with: stateNameFromPlaceholder });
 * // :placeholder="order.stateMachineState.translated.name" :background-style="x"
 * // → :placeholder="order.stateMachineState.translated.name" :state-name="order.stateMachineState.technicalName"
 */
const stateNameFromPlaceholder: PropReplacement = {
    objectVBindReason:
        'Object v-bind can hide background-style and state-name. Review the bound object and pass state-name manually.',
    fix(api, attribute, fixer) {
        if (api.ast.hasMatchingPropAttribute(api.node, 'state-name')) {
            return api.removeAttribute(fixer, attribute);
        }

        const placeholder = api.ast.findMatchingPropAttribute(api.node, 'placeholder');
        const placeholderSource =
            placeholder && api.ast.getDirectiveName(placeholder) === 'bind'
                ? api.ast.getAttributeValueSource(placeholder)
                : null;
        const stateOwner = TRANSLATED_STATE_NAME.exec(placeholderSource ?? '')?.[1];
        const backgroundStyle =
            api.ast.getDirectiveName(attribute) === 'bind'
                ? (api.ast.getAttributeValueSource(attribute) ?? '').replace(/"/g, "'")
                : '';
        const stateName = stateOwner
            ? `${stateOwner}.stateMachineState.technicalName`
            : CORE_BACKGROUND_STYLE_STATES[backgroundStyle];

        return stateName
            ? fixer.replaceText(attribute, `:state-name="${stateName}"`)
            : api.removeAttribute(fixer, attribute);
    },
};

export default componentMigration({
    id: 'component.sw-order-state-select-v2',
    component: 'sw-order-state-select-v2',
    deprecatedIn: '6.7.16.0',
    removedIn: '6.8.0',
    description:
        'The background-style and rounded-style props of sw-order-state-select-v2 are removed. The select derives the state color from state-name, the technical name of the current state.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#deprecated-state-select-styling-members-in-the-order-module'),
    ],
    onEveryUsage: [
        replaceProp({
            prop: 'background-style',
            with: stateNameFromPlaceholder,
            fix: 'unsafe-auto',
            message:
                'The "background-style" prop is removed. Pass the technical name of the current state as "state-name", e.g. :state-name="order.stateMachineState.technicalName".',
        }),
        removeProp({ prop: 'rounded-style' }),
    ],
});
