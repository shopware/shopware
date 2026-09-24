/**
 * @sw-package framework
 */
import { warn } from 'src/core/service/utils/debug.utils';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type TranslateFunction = (...args: any[]) => string;

type ComponentInstance = { $options?: { name?: string } } | undefined;

// Keys of vue-i18n's `TranslateOptions`. An object after a plural count containing only these keys is a valid
// vue-i18n 10 options object and is passed through untouched. Keep in sync with
// `eslint-rules/core-rules/no-tc-translation.js`.
const TRANSLATE_OPTION_KEYS = new Set([
    'list',
    'named',
    'plural',
    'default',
    'locale',
    'missingWarn',
    'fallbackWarn',
    'escapeParameter',
    'resolvedMessage',
    'part',
]);

const reportedDeprecations = new Set<string>();

function inComponent(componentName: string | undefined): string {
    return componentName ? ` in component "${componentName}"` : '';
}

function reportDeprecationOnce(id: string, message: string): void {
    // `warn` is silent in production anyway, returning early keeps the set from growing there.
    if (process.env.NODE_ENV === 'production' || reportedDeprecations.has(id)) {
        return;
    }

    reportedDeprecations.add(id);
    warn('Deprecation', message);
}

function isLegacyPluralCall(args: unknown[]): boolean {
    if (args.length !== 3 || typeof args[1] !== 'number') {
        return false;
    }

    const named = args[2];
    if (typeof named !== 'object' || named === null || Array.isArray(named)) {
        return false;
    }

    return Object.keys(named).some((key) => !TRANSLATE_OPTION_KEYS.has(key));
}

/**
 * vue-i18n 10 reads `t(key, plural, named)` as `t(key, plural, options)` and silently drops the named parameters,
 * so calls in the vue-i18n 8 order are swapped to `t(key, named, plural)`.
 *
 * @deprecated tag:v6.9.0 - Support for the vue-i18n 8 argument order will be removed.
 */
function normalizeLegacyArguments(instance: ComponentInstance, args: unknown[], apiName: string): unknown[] {
    if (!isLegacyPluralCall(args)) {
        return args;
    }

    const componentName = instance?.$options?.name;
    const key = String(args[0]);
    reportDeprecationOnce(
        `legacy-order:${apiName}:${componentName ?? ''}:${key}`,
        `${apiName}('${key}', plural, namedParameters) uses the vue-i18n 8 argument order${inComponent(componentName)}. ` +
            `Use ${apiName}('${key}', namedParameters, plural) instead. ` +
            'Support for the old order will be removed in v6.9.0. ' +
            'See https://vue-i18n.intlify.dev/guide/migration/breaking10',
    );

    return [args[0], args[2], args[1]];
}

/**
 * @private
 */
export function createTranslate<T extends TranslateFunction>(t: T, apiName = '$t'): T {
    return function translate(this: ComponentInstance, ...args: unknown[]) {
        return t(...normalizeLegacyArguments(this, args, apiName));
    } as T;
}

/**
 * @private
 */
export function createDeprecatedTc<T extends TranslateFunction>(t: T, apiName: string, replacement: string): T {
    const translate = createTranslate(t, replacement);

    return function tc(this: ComponentInstance, ...args: unknown[]) {
        const componentName = this?.$options?.name;
        const key = String(args[0]);

        reportDeprecationOnce(
            `tc:${apiName}:${componentName ?? ''}:${key}`,
            `${apiName}() is deprecated and will be removed in v6.9.0. ` +
                `Replace ${apiName}('${key}') with ${replacement}('${key}')${inComponent(componentName)}. ` +
                'The ESLint rule "sw-core-rules/no-tc-translation" fixes this automatically.',
        );

        return translate.apply(this, args);
    } as T;
}
