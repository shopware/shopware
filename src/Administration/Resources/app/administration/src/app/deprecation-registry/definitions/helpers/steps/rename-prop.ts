/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, RenamePropStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Renames a deprecated prop, keeping its value and binding. With `invert` the value is negated, for a
 * boolean prop whose meaning flipped; with `staticValue` the prop is replaced by a valued prop.
 *
 * @example
 * renameProp({ from: 'value', to: 'model-value' });
 * renameProp({ from: 'show-icon', to: 'hide-icon', invert: true });
 * renameProp({ from: 'small', to: 'size', staticValue: '16px' });
 */
export function renameProp({ fix = 'auto', ...config }: StepConfig<RenamePropStep>): RenamePropStep {
    return defineUsageStep<RenamePropStep>({ kind: 'rename-prop', fix, ...config }, reportRenameProp);
}

function reportRenameProp(api: ComponentUsageRuleApi, step: RenamePropStep): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, step.from);

    if (!attribute) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.from,
        replacement: step.to,
        ...planRename(api, step, attribute),
    });
}

function planRename(api: ComponentUsageRuleApi, step: RenamePropStep, attribute: TemplateNode): UsagePlan {
    if (step.staticValue !== undefined) {
        return planStaticValueRename(api, step, step.staticValue, attribute);
    }

    if (step.invert) {
        return planInvertedRename(api, step, attribute);
    }

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

function planStaticValueRename(
    api: ComponentUsageRuleApi,
    step: RenamePropStep,
    value: string,
    attribute: TemplateNode,
): UsagePlan {
    if (isExpressionBound(api, attribute)) {
        return {
            manualReason: `Expression-bound prop values can be false at runtime. Review the expression and replace it with "${value}" manually if needed.`,
        };
    }

    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide this prop value. Review the bound object and replace it with "${value}" manually if needed.`,
        };
    }

    if (api.ast.hasMatchingPropAttribute(api.node, step.to)) {
        return { fix: (fixer) => fixer.remove(attribute) };
    }

    // Two deprecated props that map to the same replacement (`small` and `large`) need a human decision.
    if (hasOtherPropRenamedToSameTarget(api, step)) {
        return {};
    }

    return {
        produces: { kind: 'prop', name: step.to },
        fix: (fixer) => fixer.replaceText(attribute, `${step.to}="${value}"`),
    };
}

function planInvertedRename(api: ComponentUsageRuleApi, step: RenamePropStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return {
            manualReason: 'Object v-bind can hide this boolean prop. Review the bound object and invert the value manually.',
        };
    }

    // A valueless boolean attribute is true, so the inverted prop keeps its false default.
    if (!attribute.value) {
        return { fix: (fixer) => fixer.remove(attribute) };
    }

    if (isBound(api, attribute)) {
        const source = api.ast.getAttributeValueSource(attribute) ?? '';

        return {
            produces: { kind: 'prop', name: step.to },
            fix: (fixer) => fixer.replaceText(attribute, `:${step.to}="!(${source})"`),
        };
    }

    const invertedLiteral = invertBooleanLiteral(String(attribute.value.value));

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

function hasOtherPropRenamedToSameTarget(api: ComponentUsageRuleApi, step: RenamePropStep): boolean {
    return api.migration.usage.some((candidate) => {
        return (
            candidate !== api.usage &&
            candidate.kind === 'rename-prop' &&
            candidate.to === step.to &&
            Boolean(api.ast.findMatchingPropAttribute(api.node, candidate.from))
        );
    });
}
