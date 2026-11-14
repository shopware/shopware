/**
 * @sw-package framework
 */
import Flags from 'src/helper/feature.helper';

/** @deprecated tag:v6.8.0 - Use the replacement. */
function missingGuard() {
    performRequest();
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
export const unguardedArrow = () => {};

const api = {
    /** @deprecated tag:v6.8.0 - Use the replacement. */
    old: function () {},
};

class Api {
    /** @deprecated tag:v6.8.0 - Use the replacement. */
    set old(value) {
        this.value = value;
    }
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
export default class OldPlugin extends Plugin {
    init() {}
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function wrongMajor() {
    window.Feature.triggerDeprecationOrThrow('v6.9.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function threePartFlag() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function missingMessage() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function emptyMessage() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', ' ');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function unrelatedReceiver() {
    other.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function shadowedImport(Flags) {
    Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function shadowedWindow(window) {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function callbackOnly() {
    nested(() => {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    });
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function nestedFunctionOnly() {
    function nested() {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    }
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function nestedClassOnly() {
    return class Nested {
        init() {
            window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
        }
    };
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function guardInAnotherMethod() {}

function unrelated() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v99.0.0 */
function unknownFlag() {}

window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');

if (legacy) {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}
