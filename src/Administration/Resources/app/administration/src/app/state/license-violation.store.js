/**
 * @package services-settings
 * @private
 * @deprecated tag:v6.7.0 - Will be replaced with Pinia store
 */
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
