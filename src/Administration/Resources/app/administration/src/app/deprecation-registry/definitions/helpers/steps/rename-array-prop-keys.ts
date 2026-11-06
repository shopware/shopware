/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-return -- option objects are untyped ESTree nodes */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, Fix, Fixer, RenameArrayPropKeysStep, StepConfig, TemplateNode } from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

const DEFAULT_UNSAFE_MESSAGE =
    'Migrate option object keys manually because this options expression is dynamic or structurally unsafe to rewrite automatically.';

/**
 * Renames keys of the objects in an array prop, such as the options of a select.
 *
 * - a literal array or object → keys renamed
 * - any other expression that names a deprecated key → manual with `unsafeMessage`
 * - any other bound expression on a converted element → manual, because its keys are unknown
 * - an element that sets one of `unlessElementHas` → skipped
 *
 * @example
 * renameArrayPropKeys({
 *     prop: 'options',
 *     renameKeys: { name: 'label', id: 'value' },
 *     unlessElementHas: ['label-property', 'value-property'],
 * });
 * // :options="[{ id: 1, name: 'A' }]" → :options="[{ value: 1, label: 'A' }]"
 * // :options="salutations" right after a conversion → manual
 */
export function renameArrayPropKeys({
    fix = 'auto',
    ...config
}: StepConfig<RenameArrayPropKeysStep>): RenameArrayPropKeysStep {
    return defineUsageStep<RenameArrayPropKeysStep>(
        { kind: 'rename-array-prop-keys', fix, ...config },
        reportRenameArrayPropKeys,
    );
}

function reportRenameArrayPropKeys(api: ComponentUsageRuleApi, step: RenameArrayPropKeysStep): void {
    const mapsKeysThroughProp = (step.unlessElementHas ?? []).some((propName) => {
        return api.ast.hasMatchingPropAttribute(api.node, propName);
    });

    if (mapsKeysThroughProp) {
        return;
    }

    const attribute = api.ast.findMatchingPropAttribute(api.node, step.prop);
    const source = attribute ? api.ast.getAttributeValueSource(attribute) : null;

    if (
        !attribute ||
        !source ||
        !(hasDeprecatedKeys(source, step.renameKeys) || isDynamicOnConvertedElement(api, attribute))
    ) {
        return;
    }

    reportUsage(api, {
        node: attribute,
        apiName: step.prop,
        ...planKeyMapping(api, step, attribute),
    });
}

/**
 * Tells whether a just converted element binds options the codemod cannot read, such as a computed
 * property. Its keys may still be the deprecated ones, so the usage needs a manual review. Elements
 * that were not converted are not flagged, because their options already follow the new API.
 *
 * @example
 * isDynamicOnConvertedElement(api, attributeOf(':options="salutationOptions"')); // true below a conversion comment
 */
function isDynamicOnConvertedElement(api: ComponentUsageRuleApi, attribute: TemplateNode): boolean {
    const expression = attribute.value?.expression as TemplateNode | undefined;

    return (
        api.ast.getDirectiveName(attribute) === 'bind' &&
        Boolean(expression) &&
        getLiteralOptionObjects(expression) === null &&
        api.isConverted()
    );
}

function planKeyMapping(api: ComponentUsageRuleApi, step: RenameArrayPropKeysStep, attribute: TemplateNode): UsagePlan {
    if (hasObjectVBind(api)) {
        return {
            manualReason: `Object v-bind can hide the "${step.prop}" prop. Review the bound object and migrate option keys manually if needed.`,
        };
    }

    const optionObjects = getLiteralOptionObjects(attribute.value?.expression as TemplateNode | undefined);
    const deprecatedProperties = optionObjects ? collectDeprecatedProperties(optionObjects, step.renameKeys) : [];

    if (!optionObjects || deprecatedProperties.length === 0 || hasReplacementKeyConflict(optionObjects, step.renameKeys)) {
        return { manualReason: step.unsafeMessage ?? DEFAULT_UNSAFE_MESSAGE };
    }

    return {
        fix: (fixer) => deprecatedProperties.map((property) => renameOptionKey(api, fixer, property, step.renameKeys)),
    };
}

function renameOptionKey(
    api: ComponentUsageRuleApi,
    fixer: Fixer,
    property: TemplateNode,
    keyMap: Record<string, string>,
): Fix {
    const from = getStaticPropertyName(property) as string;
    const to = keyMap[from];

    if (property.shorthand === true) {
        return fixer.replaceText(property, `${to}: ${from}`);
    }

    return fixer.replaceText(property.key, api.sourceCode.getText(property.key).replace(from, to));
}

function hasDeprecatedKeys(source: string, keyMap: Record<string, string>): boolean {
    return Object.keys(keyMap).some((key) => {
        const quotedKeyPattern = new RegExp(`(['"])${key}\\1\\s*:`);
        const shorthandKeyPattern = new RegExp(`(^|[{,])\\s*${key}\\s*([,}])`);
        const unquotedKeyPattern = new RegExp(`(^|[{,])\\s*${key}\\s*:`);

        return quotedKeyPattern.test(source) || shorthandKeyPattern.test(source) || unquotedKeyPattern.test(source);
    });
}

function getStaticPropertyName(property: TemplateNode): string | null {
    if (property.computed === true) {
        return null;
    }

    if (property.key?.type === 'Identifier') {
        return property.key.name as string;
    }

    if (typeof property.key?.value === 'string') {
        return property.key.value;
    }

    return null;
}

/**
 * Returns the option objects of an object or array literal; `null` for anything that is not a plain
 * literal, because only literals can be rewritten safely.
 *
 * @example
 * getLiteralOptionObjects(parse("[{ name: 'A' }]")); // [ObjectExpression]
 * getLiteralOptionObjects(parse('options.map(toOption)')); // null
 */
function getLiteralOptionObjects(expression: TemplateNode | undefined): TemplateNode[] | null {
    if (expression?.type === 'ObjectExpression') {
        return [expression];
    }

    if (expression?.type !== 'ArrayExpression') {
        return null;
    }

    const elements = expression.elements as Array<TemplateNode | null>;

    if (elements.some((element) => element?.type !== 'ObjectExpression')) {
        return null;
    }

    return elements as TemplateNode[];
}

function collectDeprecatedProperties(optionObjects: TemplateNode[], keyMap: Record<string, string>): TemplateNode[] {
    return optionObjects.flatMap((optionObject) => {
        const properties = (optionObject.properties ?? []) as TemplateNode[];

        return properties.filter((property) => {
            const propertyName = getStaticPropertyName(property);

            return property.type === 'Property' && propertyName !== null && Object.hasOwn(keyMap, propertyName);
        });
    });
}

function hasReplacementKeyConflict(optionObjects: TemplateNode[], keyMap: Record<string, string>): boolean {
    return optionObjects.some((optionObject) => {
        const properties = (optionObject.properties ?? []) as TemplateNode[];
        const propertyNames = new Set(properties.map(getStaticPropertyName));

        return Object.entries(keyMap).some(
            ([
                from,
                to,
            ]) => propertyNames.has(from) && propertyNames.has(to),
        );
    });
}
