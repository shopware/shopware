/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-call -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, NumberOptionsToOptionsPropStep, StepConfig, TemplateNode } from '../types';
import { attempt, defineUsageStep, fail, reportUsage } from '../shared';

const TWIG = /{%|%}|{#|#}/;

const MANUAL = 'Use v-model and :options="[{ value: 1, label: \'…\' }]" with numeric values.';

type Edit = { range: [number, number]; text: string };

/**
 * Turns the number select `sw-select-number-field` into `mt-select`, in one rewrite of the start tag.
 *
 * - static `<option>` children with numeric values become `:options="[{ value: 1, label: '…' }]"`
 * - an `options` prop is kept, with `value-property="id"` and `label-property="name"`
 * - the `value` forms become `model-value`, a static value a bound number
 * - all or nothing: option names as locale objects, `aside`, slots and other children keep the element unconverted
 *
 * @example
 * onConvert: [renameComponent({ from: 'sw-select-number-field', to: 'mt-select' }), numberOptionsToOptionsProp()]
 * // <sw-select-number-field v-model:value="x"><option value="2">2</option></sw-select-number-field>
 * // → <mt-select v-model="x" :options="[{ value: 2, label: '2' }]" />
 */
export function numberOptionsToOptionsProp({
    fix = 'auto',
    ...config
}: StepConfig<NumberOptionsToOptionsPropStep> = {}): NumberOptionsToOptionsPropStep {
    return defineUsageStep<NumberOptionsToOptionsPropStep>(
        { kind: 'number-options-to-options-prop', fix, ...config },
        (api) => {
            const isConverted = api.node.name !== api.migration.component;

            // On a converted element only option children can be left; the mt-select steps handle the rest
            if (isConverted && !api.node.children.some((child: TemplateNode) => child.type === 'VElement')) {
                return;
            }

            const { result: startTag, reason } = attempt(() => convert(api, api.node));

            if (reason !== null) {
                reportUsage(api, {
                    node: api.node,
                    apiName: 'option',
                    headline: `${MANUAL} Not converted automatically because of ${reason}.`,
                    blocksConversion: true,
                });

                return;
            }

            const nameEnd = (api.node.startTag.range[0] as number) + 1 + (api.node.rawName as string).length;

            reportUsage(api, {
                node: api.node.startTag,
                apiName: 'option',
                headline: 'The options and the value move into the props of mt-select.',
                fix: (fixer) => fixer.replaceTextRange([nameEnd, api.node.range[1]], startTag),
            });
        },
    );
}

function normalizedName(name: string): string {
    return name.replace(/-/g, '').toLowerCase();
}

function numericLiteral(text: unknown): string | null {
    const trimmed = String(text).trim();

    return trimmed !== '' && Number.isFinite(Number(trimmed)) ? String(Number(trimmed)) : null;
}

/**
 * The key of a static attribute or `v-bind` without `:`, or null for other directives.
 */
function attributeName(attribute: TemplateNode): string | null {
    if (!attribute.directive) {
        return attribute.key.rawName ?? attribute.key.name;
    }

    if (attribute.key.name?.name !== 'bind' || !attribute.key.argument || attribute.key.argument.type !== 'VIdentifier') {
        return null;
    }

    return attribute.key.argument.rawName ?? attribute.key.argument.name;
}

/**
 * The directive name and argument of a `v-on`/`v-model` attribute, or null.
 */
function directiveOf(attribute: TemplateNode): { directive: string; argument: string | null } | null {
    if (!attribute.directive) {
        return null;
    }

    const argument = attribute.key.argument;
    let argumentName: string | null = '';

    if (argument) {
        argumentName = argument.type === 'VIdentifier' ? (argument.rawName ?? argument.name) : null;
    }

    return { directive: attribute.key.name.name, argument: argumentName };
}

function hasLocaleObjectNames(expression: TemplateNode): boolean {
    return (
        expression.type === 'ArrayExpression' &&
        expression.elements.some((element: TemplateNode | null) => {
            return (
                element?.type === 'ObjectExpression' &&
                element.properties.some((property: TemplateNode) => {
                    return (
                        property.type === 'Property' &&
                        (property.key.name ?? property.key.value) === 'name' &&
                        property.value.type === 'ObjectExpression'
                    );
                })
            );
        })
    );
}

/**
 * Reads static `<option value="1">Label</option>` children as `{ value: 1, label: 'Label' }` sources.
 */
function readOptions(api: ComponentUsageRuleApi, element: TemplateNode): string[] {
    const inner = element.endTag ? api.sourceCode.text.slice(element.startTag.range[1], element.endTag.range[0]) : '';

    if (TWIG.test(inner) || /<!--/.test(inner)) {
        fail('Twig or comments in the options');
    }

    return element.children
        .filter((child: TemplateNode) => child.type !== 'VText' || child.value.trim() !== '')
        .map((child: TemplateNode) => {
            if (child.type !== 'VElement') {
                fail('text next to the options');
            }

            if (child.name !== 'option') {
                const slot = child.startTag.attributes.find((attribute: TemplateNode) => {
                    return directiveOf(attribute)?.directive === 'slot';
                });

                fail(
                    slot
                        ? `the #${directiveOf(slot)?.argument || 'default'} slot`
                        : `a <${child.rawName ?? child.name}> element`,
                );
            }

            const values = child.startTag.attributes.map((attribute: TemplateNode) => {
                if (attributeName(attribute) !== 'value') {
                    fail(`the ${api.sourceCode.getText(attribute.key)} attribute on option`);
                }

                const raw = attribute.directive ? attribute.value?.expression : attribute.value?.value;
                const literal = attribute.directive ? raw?.type === 'Literal' && raw.value : raw;

                return numericLiteral(literal ?? '') ?? fail('an option value that is not a number');
            });
            const content = api.ast.readContentExpression(child) ?? fail('an option label with markup');
            const value = values.at(-1);

            if (value === undefined || content.code === null) {
                fail('an option without value or label');
            }

            return `{ value: ${value}, label: ${content.code} }`;
        });
}

type FieldState = {
    edits: Edit[];
    hasOptionsProp: boolean;
    hasValueProperty: boolean;
    hasLabelProperty: boolean;
};

/**
 * `v-model:value` and `@update:value` become their `model-value` forms; other directives stay.
 */
function readDirective(
    attribute: TemplateNode,
    { directive, argument }: { directive: string; argument: string | null },
    field: FieldState,
): void {
    if (argument === null) {
        fail('a dynamic argument');
    }

    if (directive === 'model' && argument === 'value') {
        field.edits.push({ range: attribute.key.range, text: 'v-model' });
    } else if (directive === 'on' && argument === 'update:value') {
        field.edits.push({ range: attribute.key.argument.range, text: 'update:model-value' });
    } else if (directive === 'slot') {
        fail('v-slot on sw-select-number-field');
    }
}

function readValue(attribute: TemplateNode, field: FieldState): void {
    if (attribute.directive) {
        field.edits.push({ range: attribute.key.argument.range, text: 'model-value' });

        return;
    }

    const value = numericLiteral(attribute.value?.value ?? '') ?? fail('a value that is not a number');

    field.edits.push({ range: attribute.range, text: `:model-value="${value}"` });
}

function readProp(attribute: TemplateNode, field: FieldState): void {
    const name = attributeName(attribute) ?? fail('v-bind without an argument');

    switch (normalizedName(name)) {
        case 'value':
            return readValue(attribute, field);
        case 'aside':
            return fail('aside, which mt-select has no replacement for');
        case 'options':
            if (!attribute.directive || !attribute.value?.expression) {
                fail('static options');
            }

            if (hasLocaleObjectNames(attribute.value.expression)) {
                fail('option names as locale objects');
            }

            field.hasOptionsProp = true;
            return;
        case 'valueproperty':
            field.hasValueProperty = true;
            return;
        case 'labelproperty':
            field.hasLabelProperty = true;
            break;
        default:
            break;
    }
}

/**
 * Builds the rest of the `mt-select` start tag after its name, or fails with the reason it can't.
 */
function convert(api: ComponentUsageRuleApi, element: TemplateNode): string {
    const field: FieldState = { edits: [], hasOptionsProp: false, hasValueProperty: false, hasLabelProperty: false };

    element.startTag.attributes.forEach((attribute: TemplateNode) => {
        const directive = directiveOf(attribute);

        if (directive && directive.directive !== 'bind') {
            readDirective(attribute, directive, field);
        } else {
            readProp(attribute, field);
        }
    });

    const children = readOptions(api, element);

    if (children.length > 0 && field.hasOptionsProp) {
        fail('options and option children together');
    }

    if (children.length === 0 && !field.hasOptionsProp) {
        fail('missing options');
    }

    const added =
        children.length > 0
            ? [`:options="[${children.join(', ')}]"`]
            : [
                  ...(field.hasValueProperty ? [] : ['value-property="id"']),
                  ...(field.hasLabelProperty ? [] : ['label-property="name"']),
              ];

    return buildRestOfStartTag(api, element, field.edits, added);
}

/**
 * Rebuilds the start tag after its name with replaced attribute parts and added attributes, as a self-closing tag.
 * Keeps the original line breaks between the attributes.
 */
function buildRestOfStartTag(api: ComponentUsageRuleApi, element: TemplateNode, edits: Edit[], added: string[]): string {
    const text = api.sourceCode.text;
    const startTag = element.startTag;
    const attributes = startTag.attributes;
    const last = attributes[attributes.length - 1];
    const nameEnd = (startTag.range[0] as number) + 1 + (element.rawName ?? element.name).length;
    const attributesEnd = last ? (last.range[1] as number) : nameEnd;
    const separator = last ? /\s*$/.exec(text.slice(nameEnd, last.range[0]))?.[0] || ' ' : ' ';

    const attributeText = [...edits]
        .sort((a, b) => b.range[0] - a.range[0])
        .reduce(
            (result, edit) => {
                return `${result.slice(0, edit.range[0] - nameEnd)}${edit.text}${result.slice(edit.range[1] - nameEnd)}`;
            },
            text.slice(nameEnd, attributesEnd),
        );

    const tail = text.slice(attributesEnd, startTag.range[1]).replace(/\/?>$/, '');

    return `${attributeText}${added.map((attribute) => `${separator}${attribute}`).join('')}${tail || ' '}/>`;
}
