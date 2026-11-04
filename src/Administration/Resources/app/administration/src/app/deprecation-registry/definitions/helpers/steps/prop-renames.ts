/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    BooleanPropToValueStep,
    ComponentUsageRuleApi,
    InvertBooleanPropStep,
    RenamePropStep,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Renames a deprecated prop and keeps its value and binding.
 *
 * @example
 * renameProp({ from: 'value', to: 'model-value' }); // :value="x" → :model-value="x"
 */
export function renameProp({ fix = 'auto', ...config }: StepConfig<RenamePropStep>): RenamePropStep {
    return defineUsageStep<RenamePropStep>({ kind: 'rename-prop', fix, ...config }, (api, step) => {
        reportPropStep(api, step, (attribute) => planRename(api, step, attribute));
    });
}

/**
 * Renames a boolean prop whose meaning flipped and negates its value.
 *
 * @example
 * invertBooleanProp({ from: 'show-icon', to: 'hide-icon' }); // :show-icon="isShown" → :hide-icon="!(isShown)"
 */
export function invertBooleanProp({ fix = 'auto', ...config }: StepConfig<InvertBooleanPropStep>): InvertBooleanPropStep {
    return defineUsageStep<InvertBooleanPropStep>({ kind: 'invert-boolean-prop', fix, ...config }, (api, step) => {
        reportPropStep(api, step, (attribute) => planInvertedRename(api, step, attribute));
    });
}

/**
 * Replaces a boolean prop with a prop that takes a value, for example a size flag by a size.
 *
 * @example
 * booleanPropToValue({ from: 'small', to: 'size', value: '16px' }); // small → size="16px"
 */
export function booleanPropToValue({ fix = 'auto', ...config }: StepConfig<BooleanPropToValueStep>): BooleanPropToValueStep {
    return defineUsageStep<BooleanPropToValueStep>({ kind: 'boolean-prop-to-value', fix, ...config }, (api, step) => {
        reportPropStep(api, step, (attribute) => planBooleanToValue(api, step, attribute));
    });
}

function reportPropStep(
    api: ComponentUsageRuleApi,
    step: { from: string; to: string },
    plan: (attribute: TemplateNode) => UsagePlan,
): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, step.from);

    if (!attribute) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.from,
        replacement: step.to,
        ...plan(attribute),
    });
}

function planRename(api: ComponentUsageRuleApi, step: RenamePropStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide the replacement prop. Review the bound object and rename "${step.from}" to "${step.to}" manually if needed.`,
        };
    }

    return {
        produces: { kind: 'prop', name: step.to },
        fix: (fixer) => fixer.replaceText(isBound(api, attribute) ? attribute.key.argument : attribute.key, step.to),
    };
}

function planBooleanToValue(api: ComponentUsageRuleApi, step: BooleanPropToValueStep, attribute: TemplateNode): UsagePlan {
    if (isExpressionBound(api, attribute)) {
        return {
            manualReason: `Expression-bound prop values can be false at runtime. Review the expression and replace it with "${step.value}" manually if needed.`,
        };
    }

    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide this prop value. Review the bound object and replace it with "${step.value}" manually if needed.`,
        };
    }

    if (api.ast.hasMatchingPropAttribute(api.node, step.to)) {
        return { fix: (fixer) => api.removeAttribute(fixer, attribute) };
    }

    // Two deprecated props that map to the same replacement (`small` and `large`) need a human decision.
    if (hasOtherBooleanPropForSameTarget(api, step)) {
        return {};
    }

    return {
        produces: { kind: 'prop', name: step.to },
        fix: (fixer) => fixer.replaceText(attribute, `${step.to}="${step.value}"`),
    };
}

function planInvertedRename(api: ComponentUsageRuleApi, step: InvertBooleanPropStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return {
            manualReason: 'Object v-bind can hide this boolean prop. Review the bound object and invert the value manually.',
        };
    }

    // A valueless boolean attribute is true, so the inverted prop keeps its false default.
    if (!attribute.value) {
        return { fix: (fixer) => api.removeAttribute(fixer, attribute) };
    }

    const expression = attribute.value.expression as TemplateNode | undefined;
    const isBooleanLiteral = expression?.type === 'Literal' && typeof expression.value === 'boolean';

    if (isBound(api, attribute) && !isBooleanLiteral) {
        const source = api.ast.getAttributeValueSource(attribute) ?? '';

        return {
            produces: { kind: 'prop', name: step.to },
            fix: (fixer) => fixer.replaceText(attribute, `:${step.to}="!(${source})"`),
        };
    }

    const literal = isBooleanLiteral ? String(expression.value) : String(attribute.value.value);
    const invertedLiteral = invertBooleanLiteral(literal);

    if (invertedLiteral === null) {
        return {
            manualReason: `The static value "${String(attribute.value.value)}" is no boolean. Review it and set "${step.to}" manually.`,
        };
    }

    return {
        produces: { kind: 'prop', name: step.to },
        fix: (fixer) => fixer.replaceText(attribute, `:${step.to}="${invertedLiteral}"`),
    };
}

/**
 * Negates a static boolean attribute value; `null` for a value that is no boolean literal.
 *
 * @example
 * invertBooleanLiteral('false'); // 'true'
 * invertBooleanLiteral('condition'); // null
 */
function invertBooleanLiteral(value: string): 'true' | 'false' | null {
    if (value === '' || value === 'true') {
        return 'false';
    }

    if (value === 'false') {
        return 'true';
    }

    return null;
}

function isBound(api: ComponentUsageRuleApi, attribute: TemplateNode): boolean {
    return api.ast.getDirectiveName(attribute) === 'bind';
}

/**
 * Tells whether a bound attribute holds a runtime expression; `:small="true"` counts as static.
 *
 * @example
 * isExpressionBound(api, attributeOf(':small="isSmall"')); // true
 */
function isExpressionBound(api: ComponentUsageRuleApi, attribute: TemplateNode): boolean {
    if (!attribute.value || !isBound(api, attribute)) {
        return false;
    }

    const expression = attribute.value.expression as TemplateNode | undefined;

    return !(expression?.type === 'Literal' && expression.value === true);
}

function hasOtherBooleanPropForSameTarget(api: ComponentUsageRuleApi, step: BooleanPropToValueStep): boolean {
    return api.migration.usage.some((candidate) => {
        return (
            candidate !== api.usage &&
            candidate.kind === 'boolean-prop-to-value' &&
            candidate.to === step.to &&
            Boolean(api.ast.findMatchingPropAttribute(api.node, candidate.from))
        );
    });
}
