const { RuleTester } = require('eslint');
const rule = require('./require-deprecation-guard');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const guard = "Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', 'x is deprecated. Use y instead.');";

ruleTester.run('require-deprecation-guard', rule, {
    valid: [
        {
            name: 'a guarded method',
            code: `
export default {
    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        oldMethod() {
            ${guard}

            return 1;
        },
    },
};
            `,
        },
        {
            name: 'a guarded computed',
            code: `
export default {
    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        oldComputed() {
            ${guard}

            return 1;
        },
    },
};
            `,
        },
        {
            name: 'a computed accessor facade guarding both get and set',
            code: `
export default {
    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        legacyField: {
            get() {
                ${guard}

                return this._legacyField;
            },
            set(value) {
                ${guard}

                this._legacyField = value;
            },
        },
    },
};
            `,
        },
        {
            name: 'a component annotated for the deprecation plugin',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed, use mt-select instead.
 */
export default {
    template,

    deprecated: {
        version: 'v6.8.0.0',
        comment: 'Use "mt-select" instead.',
    },
};
            `,
        },
        {
            name: 'a wrapComponentConfig component annotated for the deprecation plugin',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    deprecated: 'v6.8.0.0',
});
            `,
        },
        {
            name: 'a prop annotated for the deprecation plugin',
            code: `
export default {
    props: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed. Use emptyIcon instead
         */
        emptyImagePath: {
            type: String,
            required: false,
            deprecated: {
                version: 'v6.8.0.0',
                comment: 'Use "emptyIcon" instead.',
            },
        },
    },
};
            `,
        },
        {
            name: 'a guarded exported function',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
export function email(value) {
    ${guard}

    return value;
}
            `,
        },
        {
            name: 'a private symbol takes precedence over the deprecation',
            code: `
/**
 * @private
 * @deprecated tag:v6.8.0 - Will be removed
 */
export default {
    template,
};
            `,
        },
        {
            name: 'an underscore-prefixed member needs no guard',
            code: `
export default {
    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        _internalHelper() {
            return 1;
        },
    },
};
            `,
        },
        {
            name: 'data members are static-only',
            code: `
export default {
    data() {
        return {
            /**
             * @deprecated tag:v6.8.0 - Will be removed
             */
            legacyField: null,
        };
    },
};
            `,
        },
        {
            name: 'watchers are static-only',
            code: `
export default {
    watch: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        legacyField() {
            return 1;
        },
    },
};
            `,
        },
        {
            name: 'an explicit static-only reason is accepted',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed
 * @deprecationGuard static-only - Module-level import, there is no runtime use boundary.
 */
import MiddlewareHelper from 'src/core/helper/middleware.helper';
            `,
        },
        {
            name: 'a deprecation without a tag version is ignored',
            code: `
export default {
    methods: {
        /**
         * @deprecated use something else
         */
        oldMethod() {
            return 1;
        },
    },
};
            `,
        },
    ],

    invalid: [
        {
            name: 'an unguarded method',
            code: `
export default {
    methods: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        oldMethod() {
            return 1;
        },
    },
};
            `,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'an unguarded computed',
            code: `
export default {
    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        oldComputed() {
            return 1;
        },
    },
};
            `,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'a computed accessor facade that only guards the getter',
            code: `
export default {
    computed: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        legacyField: {
            get() {
                ${guard}

                return this._legacyField;
            },
            set(value) {
                this._legacyField = value;
            },
        },
    },
};
            `,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'a guard using the wrong major flag',
            code: `
export default {
    methods: {
        /**
         * @deprecated tag:v6.9.0 - Will be removed
         */
        oldMethod() {
            ${guard}

            return 1;
        },
    },
};
            `,
            errors: [{ messageId: 'flagMismatch' }],
        },
        {
            name: 'a deprecated component without the plugin annotation',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed, use mt-select instead.
 */
export default {
    template,
};
            `,
            errors: [{ messageId: 'missingComponentOption' }],
        },
        {
            name: 'a deprecated prop without the plugin annotation',
            code: `
export default {
    props: {
        /**
         * @deprecated tag:v6.8.0 - Will be removed
         */
        emptyImagePath: {
            type: String,
            required: false,
        },
    },
};
            `,
            errors: [{ messageId: 'missingPropOption' }],
        },
        {
            name: 'an unguarded exported function',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
export function email(value) {
    return value;
}
            `,
            errors: [{ messageId: 'missingGuard' }],
        },
        {
            name: 'an exported value has no use boundary and must say so',
            code: `
/**
 * @deprecated tag:v6.8.0 - Will be removed
 */
export const POLL_BACKGROUND_INTERVAL = 30000;
            `,
            errors: [{ messageId: 'unguardableSymbol' }],
        },
    ],
});
