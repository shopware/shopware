/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

export type SmokeCase = {
    id: string;
    component: string;
    tag: string;
};

export type SmokeSelection = {
    requestedComponents: string[];
    cases: SmokeCase[];
    coverageGaps: string[];
};

export const COMPONENT_SMOKE_REGISTRY: Record<string, SmokeCase> = {
    'sw-media-library': {
        id: 'commercial-media-library',
        component: 'sw-media-library',
        tag: '@compatibility-sw-media-library',
    },
    'sw-settings-search': {
        id: 'commercial-settings-search',
        component: 'sw-settings-search',
        tag: '@compatibility-sw-settings-search',
    },
};

export function resolveComponentSmokeSelection(components: string[]): SmokeSelection {
    const requestedComponents = unique(components);
    const cases: SmokeCase[] = [];
    const coverageGaps: string[] = [];

    requestedComponents.forEach((component) => {
        const smokeCase = COMPONENT_SMOKE_REGISTRY[component];

        if (smokeCase) {
            cases.push(smokeCase);

            return;
        }

        coverageGaps.push(component);
    });

    return {
        requestedComponents,
        cases,
        coverageGaps,
    };
}

function unique(values: string[]): string[] {
    return [...new Set(values)];
}
