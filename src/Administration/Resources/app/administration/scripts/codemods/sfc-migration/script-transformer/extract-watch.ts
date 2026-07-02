import type { ObjectLiteralElementLike, ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { extractInlineFunctionHandler } from './extract-function-handler';
import { getPropertyName, isDefined } from './helpers';
import type { ExtractWatchPropsResult, WatchProp } from './types';

function parseWatchBooleanOption(
    optionName: 'deep' | 'immediate',
    optionProp: ObjectLiteralElementLike | undefined,
): { value?: boolean; unsupportedReason?: string } {
    if (!optionProp) {
        return {};
    }

    if (!optionProp.isKind(SyntaxKind.PropertyAssignment)) {
        return { unsupportedReason: `${optionName} must be a boolean literal` };
    }

    const initializerText = optionProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()?.getText();

    // Runtime expressions can depend on component state or imports. Preserve
    // semantics by requiring manual review instead of guessing a static value.
    if (initializerText === 'true') {
        return { value: true };
    }

    if (initializerText === 'false') {
        return { value: false };
    }

    return { unsupportedReason: `${optionName} must be a boolean literal` };
}

export function extractWatchProps(optionsObj: ObjectLiteralExpression): ExtractWatchPropsResult {
    const watchProp = optionsObj.getProperty('watch');
    // TODO: Silent ignore: shorthand/non-property `watch` declarations are
    // treated as absent instead of being reported as unsupported.
    if (!watchProp?.isKind(SyntaxKind.PropertyAssignment)) {
        return { watchProps: [], unsupportedEntries: [] };
    }

    const watchObj = watchProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!watchObj) {
        return { watchProps: [], unsupportedEntries: ['watch must be an object literal'] };
    }

    const result: WatchProp[] = [];
    const unsupportedEntries: string[] = [];
    for (const p of watchObj.getProperties()) {
        if (p.isKind(SyntaxKind.MethodDeclaration)) {
            const method = p.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({
                name: getPropertyName(method),
                paramsText: method
                    .getParameters()
                    // TODO: Silent ignore: getName() drops destructuring,
                    // default values, and rest syntax from watcher parameters.
                    .map((param) => param.getName())
                    .join(', '),
                bodyText: method.getBodyText() ?? '',
            });
        } else if (p.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = p.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const name = getPropertyName(pa);
            const stringHandler = pa.getInitializerIfKind(SyntaxKind.StringLiteral);

            if (stringHandler) {
                result.push({
                    name,
                    paramsText: '',
                    handlerName: stringHandler.getLiteralValue(),
                });
                continue;
            }

            const innerObj = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
            if (!innerObj) {
                unsupportedEntries.push(`${name}: unsupported watcher definition`);
                continue;
            }

            const handlerProp = innerObj.getProperty('handler');
            if (!handlerProp) {
                unsupportedEntries.push(`${name}: missing watcher handler`);
                continue;
            }

            const deepProp = innerObj.getProperty('deep');
            const immediateProp = innerObj.getProperty('immediate');
            const deepOption = parseWatchBooleanOption('deep', deepProp);
            const immediateOption = parseWatchBooleanOption('immediate', immediateProp);
            const unsupportedOptionReasons = [
                deepOption.unsupportedReason,
                immediateOption.unsupportedReason,
            ].filter(isDefined);

            if (unsupportedOptionReasons.length > 0) {
                unsupportedOptionReasons.forEach((reason) => {
                    unsupportedEntries.push(`${name}: ${reason}`);
                });
                continue;
            }

            const watchEntry: WatchProp = {
                name,
                paramsText: '',
                deep: deepOption.value,
                immediate: immediateOption.value,
            };

            if (handlerProp.isKind(SyntaxKind.MethodDeclaration)) {
                const handler = handlerProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                watchEntry.paramsText = handler
                    .getParameters()
                    // TODO: Silent ignore: getName() drops destructuring,
                    // default values, and rest syntax from object-form watcher
                    // handler parameters.
                    .map((param) => param.getName())
                    .join(', ');
                watchEntry.bodyText = handler.getBodyText() ?? '';
                watchEntry.isAsync = handler.isAsync();
                result.push(watchEntry);
                continue;
            }

            if (handlerProp.isKind(SyntaxKind.PropertyAssignment)) {
                const handlerAssignment = handlerProp.asKindOrThrow(SyntaxKind.PropertyAssignment);
                const handlerValue = handlerAssignment.getInitializerIfKind(SyntaxKind.StringLiteral);

                if (handlerValue) {
                    watchEntry.handlerName = handlerValue.getLiteralValue();
                    result.push(watchEntry);
                    continue;
                }

                const inlineHandler = handlerAssignment.getInitializer();

                if (
                    inlineHandler?.isKind(SyntaxKind.FunctionExpression) ||
                    inlineHandler?.isKind(SyntaxKind.ArrowFunction)
                ) {
                    Object.assign(watchEntry, extractInlineFunctionHandler(inlineHandler));
                    result.push(watchEntry);
                    continue;
                }
            }

            unsupportedEntries.push(`${name}: unsupported watcher handler shape`);
        } else {
            unsupportedEntries.push(`${p.getText()}: unsupported watcher entry`);
            continue;
        }
    }
    return { watchProps: result, unsupportedEntries };
}
