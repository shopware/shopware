import template from './sw-module-level-code.html.twig';

/**
 * @sw-package framework
 */

// Only the conversion reads `Component`, so its destructure is dropped; `Criteria` moves along.
const { Component } = Shopware;
const { Criteria } = Shopware.Data;

type Row = { id: string };

const MAX_ROWS = 25 as number;
const LABEL = 'sw-module-level-code';

// One Map per module, not per instance: this has to stay module-level code.
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

// Registered once per module load, not on every mount.
Shopware.Service('loginService').addOnLoginListener(() => sharedCache.clear());
