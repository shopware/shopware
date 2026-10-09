import template from './sw-module-level-code.html.twig';

/**
 * @sw-package framework
 */

// Hoistable: imports, type-only declarations and const bindings with a pure read as initializer.
const { Component } = Shopware;
const { Criteria } = Shopware.Data;

type Row = { id: string };

const MAX_ROWS = 25 as number;
const LABEL = 'sw-module-level-code';

// Not hoistable: a fresh Map per component instance instead of one shared module singleton.
const sharedCache = new Map<string, Row>();

function buildCriteria(): unknown {
    return new Criteria(1, MAX_ROWS);
}

export default Component.wrapComponentConfig({
    template,

    data(): { label: string } {
        return {
            label: LABEL,
        };
    },

    methods: {
        cache(): Map<string, Row> {
            return sharedCache;
        },

        criteria(): unknown {
            return buildCriteria();
        },
    },
});

// Not hoistable either: registering a listener here re-runs it on every mount.
Shopware.Service('loginService').addOnLoginListener(() => sharedCache.clear());
