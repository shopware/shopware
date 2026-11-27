/**
 * @sw-package framework
 */

/** @deprecated tag:v6.8.0 - Use the replacement. */
export default function conditionalDefaultExport() {
    if (legacy) {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    }
}
