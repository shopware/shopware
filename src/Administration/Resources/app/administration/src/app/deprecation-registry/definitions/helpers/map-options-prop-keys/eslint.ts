/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import type { MapOptionsPropKeysConfig } from './index';
import { componentUsageMessage, hasObjectVBind, usageFixesAutomatically } from '../shared';

/** The usage `mapOptionsPropKeys` builds: its config with the `unlessProps` default filled in. */
export type MapOptionsPropKeysUsage = DeprecationUsage &
    MapOptionsPropKeysConfig &
    Required<Pick<MapOptionsPropKeysConfig, 'unlessProps'>>;

function hasDeprecatedKeys(source: string, keyMap: Record<string, string>): boolean {
    return Object.keys(keyMap).some((key) => {
        const quotedKeyPattern = new RegExp(`(['"])${key}\\1\\s*:`);
        const shorthandKeyPattern = new RegExp(`(^|[{,])\\s*${key}\\s*([,}])`);
        const unquotedKeyPattern = new RegExp(`(^|[{,])\\s*${key}\\s*:`);

        return quotedKeyPattern.test(source) || shorthandKeyPattern.test(source) || unquotedKeyPattern.test(source);
    });
}

function getStaticPropertyName(property: Record<string, any>): string | null {
    if (property.computed === true) {
        return null;
    }

    if (property.key?.type === 'Identifier') {
        return property.key.name;
    }

    if (typeof property.key?.value === 'string') {
        return property.key.value;
    }

    return null;
}

function getSafeOptionObjects(expression: Record<string, any> | undefined): Array<Record<string, any>> | null {
    if (!expression) {
        return null;
    }

    if (expression.type === 'ObjectExpression') {
        return [expression];
    }

    if (expression.type !== 'ArrayExpression') {
        return null;
    }

    const optionObjects = expression.elements;

    if (!Array.isArray(optionObjects) || optionObjects.some((element) => element?.type !== 'ObjectExpression')) {
        return null;
    }

    return optionObjects;
}

function collectDeprecatedProperties(
    optionObjects: Array<Record<string, any>>,
    keyMap: Record<string, string>,
): Array<Record<string, any>> {
    return optionObjects.flatMap((optionObject) => {
        if (!Array.isArray(optionObject.properties)) {
            return [];
        }

        return optionObject.properties.filter((property: Record<string, any>) => {
            const propertyName = getStaticPropertyName(property);

            return property.type === 'Property' && typeof propertyName === 'string' && Object.hasOwn(keyMap, propertyName);
        });
    });
}

function hasReplacementKeyConflict(optionObjects: Array<Record<string, any>>, keyMap: Record<string, string>): boolean {
    return optionObjects.some((optionObject) => {
        if (!Array.isArray(optionObject.properties)) {
            return false;
        }

        const propertyNames = new Set(
            optionObject.properties
                .map((property: Record<string, any>) => getStaticPropertyName(property))
                .filter((propertyName: string | null): propertyName is string => typeof propertyName === 'string'),
        );

        return Object.entries(keyMap).some(
            ([
                from,
                to,
            ]) => propertyNames.has(from) && propertyNames.has(to),
        );
    });
}

function getUnsafeMessage(usageConfig: MapOptionsPropKeysUsage): string {
    return (
        usageConfig.unsafeMessage ??
        'Migrate option object keys manually because this options expression is dynamic or structurally unsafe to rewrite automatically.'
    );
}

export function createMapOptionsPropKeysEslint(usageConfig: MapOptionsPropKeysUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const keyMap = usageConfig.from;
            const hasKeyMappingProp = usageConfig.unlessProps.some((propName) => {
                return api.ast.hasMatchingPropAttribute(api.node, propName);
            });

            if (hasKeyMappingProp) {
                return;
            }

            const attribute = api.ast.findMatchingPropAttribute(api.node, usageConfig.prop);

            if (!attribute) {
                return;
            }

            const source = api.ast.getAttributeValueSource(attribute);

            if (!source || !hasDeprecatedKeys(source, keyMap)) {
                return;
            }

            const expression = attribute.value?.expression as Record<string, any> | undefined;
            const optionObjects = getSafeOptionObjects(expression);
            const deprecatedProperties = optionObjects ? collectDeprecatedProperties(optionObjects, keyMap) : [];
            const hasKeyConflict = optionObjects ? hasReplacementKeyConflict(optionObjects, keyMap) : false;
            const objectVBindMessage = hasObjectVBind(api)
                ? `Object v-bind can hide the "${usageConfig.prop}" prop. Review the bound object and migrate option keys manually if needed.`
                : undefined;
            const canFixSafely =
                optionObjects !== null && deprecatedProperties.length > 0 && !hasKeyConflict && !objectVBindMessage;
            const reportUsageConfig: DeprecationUsage = canFixSafely
                ? usageConfig
                : {
                      ...usageConfig,
                      fix: 'manual',
                      message: objectVBindMessage ?? getUnsafeMessage(usageConfig),
                  };

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, reportUsageConfig, usageConfig.prop),
                fix(fixer: Record<string, any>) {
                    if (
                        !usageFixesAutomatically(api, reportUsageConfig) ||
                        api.ast.getDirectiveName(attribute) !== 'bind' ||
                        !canFixSafely
                    ) {
                        return null;
                    }

                    return deprecatedProperties.flatMap((property) => {
                        const from = getStaticPropertyName(property);
                        const to = typeof from === 'string' ? keyMap[from] : undefined;

                        if (typeof from !== 'string' || typeof to !== 'string') {
                            return [];
                        }

                        if (property.shorthand === true) {
                            return [fixer.replaceText(property, `${to}: ${from}`)];
                        }

                        const keyText = api.sourceCode.getText(property.key);
                        const replacement = keyText.replace(from, to);

                        return [fixer.replaceText(property.key, replacement)];
                    });
                },
            });
        },
    };
}
