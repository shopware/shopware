/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export type RuntimeAllowlistEntry = {
    pattern: string;
    reason: string;
};

export type RuntimeErrorSplit = {
    regressions: string[];
    knownUnsupported: Array<{
        error: string;
        reason: string;
    }>;
};

export function splitRuntimeErrors(errors: string[], allowlist: RuntimeAllowlistEntry[]): RuntimeErrorSplit {
    return errors.reduce<RuntimeErrorSplit>((result, error) => {
        const match = allowlist.find((entry) => new RegExp(entry.pattern).test(error));

        if (match) {
            result.knownUnsupported.push({
                error,
                reason: match.reason,
            });

            return result;
        }

        result.regressions.push(error);

        return result;
    }, {
        regressions: [],
        knownUnsupported: [],
    });
}
