/**
 * @sw-package framework
 */
import Feature from 'src/helper/feature.helper';
import Flags from './helper/feature.helper.js';

function supported() {}

/** @param {string} old @deprecated tag:v6.8.0 - Parameter only. */
function supportedWithLegacyParameter(old) {}

/**
 * Supported API.
 * @param {string} old @deprecated tag:v6.8.0 - Parameter only.
 */
function supportedWithMultilineParameterAnnotation(old) {}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function guardedFunction() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
export function guardAfterOtherStatements() {
    prepare();
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
const conditionalArrow = () => {
    if (legacy) {
        prepare();
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    }
};

/** @deprecated tag:v6.8.0 - Use the replacement. */
const functionExpression = function () {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
};

const api = {
    /** @deprecated tag:v6.8.0 - Use the replacement. */
    oldMethod() {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    },
};

class Api {
    /** @deprecated tag:v6.8.0 - Use the replacement. */
    conditionalMethod() {
        prepare();
        if (legacy) {
            window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
        }
    }

    /** @deprecated tag:v6.8.0 - Use the replacement. */
    static oldMethod() {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    }

    /** @deprecated tag:v6.8.0 - Use the replacement. */
    get old() {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
        return value;
    }

    /** @deprecated tag:v6.8.0 - Use the replacement. */
    set old(value) {
        if (legacy) {
            window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
        }
        this.value = value;
    }

    /** @private @deprecated tag:v6.8.0 */
    privateMethod() {}

    static options = {
        /** @deprecated tag:v6.8.0 - Use the replacement. */
        oldDefault: null,
    };

    /** @deprecated tag:v6.8.0 - Use the replacement. */
    oldField = null;
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
export default class OldPlugin extends Plugin {
    init() {
        prepare();
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    }
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
class ConditionalPlugin extends Plugin {
    init() {
        if (legacy) {
            window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
        }
    }
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
import Old from 'old';

/** @deprecated tag:v6.8.0 - Use the replacement. */
class OldHelper {
    static supported() {}
}

/** @deprecated tag:v6.8.0.0 */
function fourPartVersion() {
    window.Feature.triggerDeprecationOrThrow('v6.8.0.0', message);
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
function defaultImport() {
    Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

/** @deprecated tag:v6.8.0 - Use the replacement. */
const relativeImportArrow = () => Flags.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');

/** @deprecated tag:v6.8.0 - Use the replacement. */
function importDeclaredLater() {
    LateFeature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
}

import LateFeature from 'src/helper/feature.helper';

function callbackFactory() {
    return () => {
        window.Feature.triggerDeprecationOrThrow('v6.8.0.0', 'Use the replacement.');
    };
}
