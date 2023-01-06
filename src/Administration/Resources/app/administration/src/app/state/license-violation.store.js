/**
 * @package merchant-services
 * @deprecated tag:v6.6.0 - Will be private
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    namespaced: true,
    state: {
        violations: [],
        warnings: [],
        other: [],
    },

    mutations: {
        setViolations(state, violations) {
            state.violations = violations;
        },

        setWarnings(state, warnings) {
            state.warnings = warnings;
        },

        setOther(state, other) {
            state.other = other;
        },

        removeViolations(state) {
            state.violations = [];
        },

        removeWarnings(state) {
            state.warnings = [];
        },

        removeOther(state) {
            state.other = [];
        },
    },
};
