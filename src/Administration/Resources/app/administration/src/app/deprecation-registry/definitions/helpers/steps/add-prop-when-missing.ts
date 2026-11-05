/* eslint-disable @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { AddPropWhenMissingStep, ComponentUsageRuleApi, Fix, Fixer, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, insertAttributeAtEnd, reportUsage } from '../shared';

/**
 * Adds a prop the element does not set, to keep a default of the old component that the new one does
 * not share. Put it into `onConvert`, so hand-written usages of the new component keep its default.
 *
 * - `prop`, or one of `unlessElementHas`, already set → skipped
 * - `value` unset → a valueless boolean attribute
 * - an object `v-bind` → added in front of it, so the bound object still wins; manual when the bound
 *   object can hide one of `unlessElementHas`
 * - `reviewHint` → a review comment above the element
 *
 * @example
 * addPropWhenMissing({ prop: 'variant', value: 'secondary' });
 * // <mt-button> → <mt-button variant="secondary">
 * addPropWhenMissing({ prop: 'hide-clearable-button' });
 * // <mt-select> → <mt-select hide-clearable-button>
 * addPropWhenMissing({ prop: 'size', value: '24px', unlessElementHas: ['size', 'small', 'large'] });
 * // <mt-icon small> → unchanged
 */
export function addPropWhenMissing({ fix = 'auto', ...config }: StepConfig<AddPropWhenMissingStep>): AddPropWhenMissingStep {
    return defineUsageStep<AddPropWhenMissingStep>({ kind: 'add-prop-when-missing', fix, ...config }, reportMissingProp);
}

function reportMissingProp(api: ComponentUsageRuleApi, step: AddPropWhenMissingStep): void {
    const unlessProps = step.unlessElementHas ?? [step.prop];
    const setsProp = unlessProps.some((propName) => api.ast.hasMatchingPropAttribute(api.node, propName));

    if (setsProp) {
        return;
    }

    reportUsage(api, {
        node: api.node,
        apiName: step.prop,
        ...planAddition(api, step, unlessProps),
    });
}

function planAddition(api: ComponentUsageRuleApi, step: AddPropWhenMissingStep, unlessProps: string[]): UsagePlan {
    const objectVBind = api.ast.findObjectVBindAttribute(api.node) ?? null;
    const hasFallbackProps = unlessProps.some((propName) => propName !== step.prop);

    if (objectVBind && hasFallbackProps) {
        return {
            manualReason: `Object v-bind can hide one of the fallback props. Review the bound object and add "${step.prop}" manually if needed.`,
        };
    }

    return {
        produces: { kind: 'prop', name: step.prop },
        fix: (fixer) => {
            const propFix = insertProp(api, fixer, step, objectVBind);

            if (step.reviewHint === undefined) {
                return propFix;
            }

            return [
                propFix,
                api.insertCodemodCommentBefore(fixer, api.node, step.reviewHint),
            ];
        },
    };
}

/**
 * Inserts the prop at the configured position. With an object v-bind it goes in front of the bound
 * object, so values in the object still win.
 */
function insertProp(
    api: ComponentUsageRuleApi,
    fixer: Fixer,
    step: AddPropWhenMissingStep,
    objectVBind: TemplateNode | null,
): Fix {
    const propSource = step.value === undefined ? step.prop : `${step.bind ? ':' : ''}${step.prop}="${step.value}"`;

    if (objectVBind) {
        const startTagLine = api.node.startTag.loc?.start?.line as number | undefined;
        const objectVBindStart = objectVBind.loc?.start as { line: number; column: number } | undefined;
        const onOwnLine = objectVBindStart && startTagLine !== undefined && objectVBindStart.line > startTagLine;
        const separator = onOwnLine ? `\n${' '.repeat(objectVBindStart.column)}` : ' ';

        return fixer.insertTextBefore(objectVBind, `${propSource}${separator}`);
    }

    if (step.insertPosition !== 'after-name') {
        return insertAttributeAtEnd(api, fixer, propSource);
    }

    return fixer.insertTextAfterRange(
        [
            afterNameOffset(api),
            afterNameOffset(api),
        ],
        ` ${propSource}`,
    );
}

function afterNameOffset(api: ComponentUsageRuleApi): number {
    return (api.node.startTag.range[0] as number) + `<${api.node.name}`.length;
}
