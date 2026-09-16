/**
 * @sw-package framework
 *
 * @module core/feature-config
 */

import { warn } from 'src/core/service/utils/debug.utils';

/**
 * A static registry containing a list of all registered flags and the associated activation state
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class Feature {
    static flags: { [featureName: string]: boolean } = {};

    /**
     * Off under Jest, where an unexpected `console.warn` fails a test and the suite covers both sides
     * of a flag on purpose. The next-major error is never suppressed.
     *
     * @private
     */
    static emitDeprecations = process.env.NODE_ENV !== 'test';

    static init(flagConfig: { [featureName: string]: boolean }): void {
        Object.entries(flagConfig).forEach(
            ([
                flagName,
                isActive,
            ]) => {
                this.flags[flagName.toUpperCase()] = isActive;
            },
        );
    }

    static getAll(): { [featureName: string]: boolean } {
        return this.flags;
    }

    static isActive(flagName: string): boolean {
        flagName = flagName.toUpperCase();

        if (!this.flags.hasOwnProperty(flagName)) {
            // if not set, its false
            return false;
        }

        return this.flags[flagName];
    }

    /**
     * Guards a deprecated API at the boundary where it is consumed. Warns while `majorFlag` is
     * inactive and throws once it is active.
     *
     * @private
     */
    static triggerDeprecationOrThrow(majorFlag: string, message: string): void {
        reportDeprecation(this.isActive(majorFlag), message);
    }
}

/**
 * Messages already reported in this session. A deprecated getter or computed property can be read on
 * every re-render, so without this the console fills up with the same notice.
 */
const reportedDeprecations = new Set<string>();

/**
 * Takes the resolved flag state rather than the flag name, because the static `Feature` registry and
 * the injectable `FeatureService` need the same behaviour while resolving flags differently.
 *
 * @private
 */
export function reportDeprecation(isMajorActive: boolean, message: string): void {
    if (isMajorActive) {
        throw new Error(`Tried to access deprecated functionality: ${message}`);
    }

    if (!Feature.emitDeprecations) {
        return;
    }

    if (reportedDeprecations.has(message)) {
        return;
    }

    reportedDeprecations.add(message);

    const callSite = resolveCallSite();

    // The canonical emitter: this is the call every other deprecation notice routes through.
    // eslint-disable-next-line sw-deprecation-rules/no-manual-deprecation-notices
    warn('Deprecation', callSite ? `${message}\n    at ${callSite}` : message);
}

/**
 * Returns the first stack frame outside of this module, so the notice points at the code that used the
 * deprecated API instead of at the helper.
 */
function resolveCallSite(): string {
    const frames = new Error().stack?.split('\n').slice(1) ?? [];
    const callSite = frames.find((frame) => !frame.includes('core/feature') && !frame.includes('feature.service'));

    return callSite?.trim() ?? '';
}
