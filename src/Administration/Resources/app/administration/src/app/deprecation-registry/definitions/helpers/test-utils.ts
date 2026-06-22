/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationMigration, DeprecationUsage, MigrationTransformResult } from './types';

type FakeApiConfig = {
    usage: DeprecationUsage;
    node?: Record<string, any>;
    migration?: DeprecationMigration;
    attribute?: Record<string, any>;
    eventAttribute?: Record<string, any>;
    modelAttribute?: Record<string, any>;
    slot?: Record<string, any>;
    disabledFix?: boolean;
    existingProps?: string[];
    attributeValueSource?: string | null;
    transform?: MigrationTransformResult | null;
};

export function createAttribute(name: string, value: unknown = null): Record<string, any> {
    return {
        key: {
            name,
            argument: {
                name,
            },
        },
        value,
        range: [1, 2],
        loc: {
            start: {
                line: 1,
                column: 4,
            },
        },
    };
}

export function createDirectiveAttribute(directive: string, argument?: string, value: unknown = null): Record<string, any> {
    return {
        key: {
            name: {
                name: directive,
            },
            ...(argument ? { argument: { name: argument } } : {}),
        },
        value,
        range: [1, 2],
        loc: {
            start: {
                line: 1,
                column: 4,
            },
        },
    };
}

export function createSlot(slotName: string, children: Array<Record<string, any>> = []): Record<string, any> {
    return {
        type: 'VElement',
        name: 'template',
        startTag: {
            range: [3, 4],
            loc: {
                start: {
                    column: 8,
                },
            },
        },
        children,
        slotName,
    };
}

export function createFixer(): Record<string, jest.Mock> {
    return {
        remove: jest.fn((target) => ({ method: 'remove', target })),
        replaceText: jest.fn((target, text) => ({ method: 'replaceText', target, text })),
        insertTextAfter: jest.fn((target, text) => ({ method: 'insertTextAfter', target, text })),
        insertTextAfterRange: jest.fn((range, text) => ({ method: 'insertTextAfterRange', range, text })),
        insertTextBefore: jest.fn((target, text) => ({ method: 'insertTextBefore', target, text })),
        insertTextBeforeRange: jest.fn((range, text) => ({ method: 'insertTextBeforeRange', range, text })),
    };
}

function getFakeSourceText(node: Record<string, any>): string {
    if (typeof node?.name === 'string') {
        return node.name;
    }

    if (typeof node?.value === 'string') {
        return node.value;
    }

    return 'expressionValue';
}

export function createRuleApi(config: FakeApiConfig): ComponentUsageRuleApi & { reports: Array<Record<string, any>> } {
    const reports: Array<Record<string, any>> = [];
    const attribute = config.attribute;
    const existingProps = new Set(config.existingProps ?? []);
    const node = config.node ?? {
        name: 'mt-test',
        startTag: {
            range: [0, 10],
            loc: {
                start: {
                    column: 0,
                },
            },
            attributes: attribute ? [attribute] : [],
        },
        children: config.slot ? [config.slot] : [],
    };

    return {
        reports,
        context: {
            options: config.disabledFix ? ['disableFix'] : [],
            sourceCode: {
                text: 'routeName',
                getText: jest.fn((sourceNode) => getFakeSourceText(sourceNode as Record<string, any>)),
                ast: {
                    templateBody: {
                        comments: [],
                    },
                },
            },
        },
        sourceCode: {
            text: 'routeName',
            getText: jest.fn((sourceNode) => getFakeSourceText(sourceNode as Record<string, any>)),
            ast: {
                templateBody: {
                    comments: [],
                },
            },
        },
        node,
        migration: config.migration ?? {
            id: 'component.test',
            deprecatedIn: '6.7.0',
            removedIn: '6.8.0',
            description: 'Test migration.',
            usage: [config.usage],
        },
        usage: config.usage,
        appendRegistryContext: jest.fn((message) => message),
        reportWithDuplicateReplacementGuard: jest.fn((descriptor) => {
            reports.push(descriptor);
        }),
        isFixDisabled: jest.fn(() => config.disabledFix === true),
        getTransformResult: jest.fn((usageConfig) => {
            if (config.transform !== undefined) {
                return config.transform;
            }

            if (typeof usageConfig.transform === 'function') {
                return usageConfig.transform({
                    phase: 'fix',
                    valueKind: 'static',
                    hasObjectVBind: false,
                });
            }

            return null;
        }),
        ast: {
            findMatchingPropAttribute: jest.fn((_node, propName) => {
                if (!attribute) {
                    return undefined;
                }

                return attribute.matchName === propName || attribute.key?.name === propName || attribute.key?.argument?.name === propName
                    ? attribute
                    : undefined;
            }),
            hasMatchingPropAttribute: jest.fn((_node, propName) => existingProps.has(propName)),
            findMatchingEventAttribute: jest.fn((_node, eventName) => {
                return config.eventAttribute?.matchName === eventName ? config.eventAttribute : undefined;
            }),
            findMatchingVModelAttribute: jest.fn((_node, argumentName) => {
                return config.modelAttribute?.matchName === argumentName ? config.modelAttribute : undefined;
            }),
            findSlot: jest.fn((_node, slotName) => {
                return config.slot?.slotName === slotName ? config.slot : undefined;
            }),
            hasCodemodComment: jest.fn(() => false),
            getAttributeValueSource: jest.fn(() => config.attributeValueSource ?? 'sourceValue'),
            getCondensedTextContent: jest.fn(() => 'Slot content'),
            getDirectiveName: jest.fn((attr) => {
                return attr?.key?.name?.name ?? null;
            }),
            getFirstElementChildWithoutSlot: jest.fn(() => undefined),
            getStaticAttributeName: jest.fn((attr) => {
                return typeof attr?.key?.name === 'string' ? attr.key.name : null;
            }),
            getDirectiveArgumentName: jest.fn((attr) => {
                return attr?.key?.argument?.name ?? null;
            }),
        },
    };
}
