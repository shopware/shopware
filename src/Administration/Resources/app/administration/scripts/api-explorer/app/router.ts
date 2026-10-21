/**
 * @sw-package framework
 */

import { onBeforeUnmount, ref, type Ref } from 'vue';
import type { ApiChange } from '../diff/types';

export type Route =
    | { name: 'overview' }
    | { name: 'blocks' }
    | { name: 'components' }
    | { name: 'component'; component: string; block: string | null }
    | { name: 'shopware'; path: string | null };

export interface Comparison {
    base: string;
    head: string;
}

export interface HashLocation {
    route: Route;
    /** Null unless the hash names both refs. */
    comparison: Comparison | null;
}

/** Every generated link carries the active comparison, so a copied URL reproduces what was on screen. */
const activeComparison = ref<Comparison | null>(null);

export function setActiveComparison(comparison: Comparison | null): void {
    activeComparison.value = comparison;
}

function splitHash(hash: string): [string, URLSearchParams] {
    const raw = hash.replace(/^#/, '');
    const queryIndex = raw.indexOf('?');

    return queryIndex === -1
        ? [
              raw,
              new URLSearchParams(),
          ]
        : [
              raw.slice(0, queryIndex),
              new URLSearchParams(raw.slice(queryIndex + 1)),
          ];
}

export function parseHash(hash: string): HashLocation {
    const [
        rawPath,
        query,
    ] = splitHash(hash);
    const segments = rawPath.split('/').filter(Boolean).map(decodeURIComponent);
    const base = query.get('base');
    const head = query.get('head');
    const comparison = base && head ? { base, head } : null;
    let route: Route;

    switch (segments[0]) {
        case 'blocks':
            route = { name: 'blocks' };
            break;
        case 'components':
            route = segments[1]
                ? { name: 'component', component: segments[1], block: query.get('block') }
                : { name: 'components' };
            break;
        case 'shopware':
            route = { name: 'shopware', path: query.get('path') };
            break;
        default:
            route = { name: 'overview' };
    }

    return { route, comparison };
}

export function hrefFor(route: Route, comparison: Comparison | null = activeComparison.value): string {
    const params = new URLSearchParams();
    let path: string;

    switch (route.name) {
        case 'blocks':
            path = '/blocks';
            break;
        case 'components':
            path = '/components';
            break;
        case 'component':
            path = `/components/${encodeURIComponent(route.component)}`;
            if (route.block) {
                params.set('block', route.block);
            }
            break;
        case 'shopware':
            path = '/shopware';
            if (route.path) {
                params.set('path', route.path);
            }
            break;
        default:
            path = '/';
    }

    if (comparison) {
        params.set('base', comparison.base);
        params.set('head', comparison.head);
    }

    const query = params.toString();

    return `#${path}${query ? `?${query}` : ''}`;
}

export function hrefForChange(change: ApiChange): string {
    if (change.area === 'shopwareObject') {
        return hrefFor({ name: 'shopware', path: change.member });
    }

    return hrefFor({
        name: 'component',
        component: change.owner,
        block: change.kind === 'block' ? change.member : null,
    });
}

export function useHashLocation(): Ref<HashLocation> {
    const location = ref<HashLocation>(parseHash(window.location.hash));
    const update = (): void => {
        location.value = parseHash(window.location.hash);
    };

    window.addEventListener('hashchange', update);
    onBeforeUnmount(() => window.removeEventListener('hashchange', update));

    return location;
}

/** Writes the comparison into the current URL without adding a history entry. */
export function replaceComparisonInHash(comparison: Comparison): void {
    const { route } = parseHash(window.location.hash);
    window.history.replaceState(window.history.state, '', hrefFor(route, comparison));
}
