/* eslint-disable @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, Fix, Fixer, MissingPropStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, insertAttributeAtEnd, reportUsage } from '../shared';

/**
 * Adds a prop the element does not set, to keep a default of the deprecated component that the
 * replacement does not share.
 *
 * @example
 * missingProp({ prop: 'variant', value: 'secondary' });
 * missingProp({ prop: 'hide-clearable-button' }); // a valueless boolean attribute
 * missingProp({ prop: 'is-opened', value: 'true', bind: true, insertPosition: 'after-name', unlessProps: ['is-opened', 'open'] });
 */
export function missingProp({ fix = 'auto', ...config }: StepConfig<MissingPropStep>): MissingPropStep {
    return defineUsageStep<MissingPropStep>({ kind: 'missing-prop', fix, ...config }, reportMissingProp);
}

function reportMissingProp(api: ComponentUsageRuleApi, step: MissingPropStep): void {
    const unlessProps = step.unlessProps ?? [step.prop];
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

function planAddition(api: ComponentUsageRuleApi, step: MissingPropStep, unlessProps: string[]): UsagePlan {
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
function insertProp(api: ComponentUsageRuleApi, fixer: Fixer, step: MissingPropStep, objectVBind: TemplateNode | null): Fix {
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
