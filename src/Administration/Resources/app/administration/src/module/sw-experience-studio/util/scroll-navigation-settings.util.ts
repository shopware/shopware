import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutEntity } from './content-layout-repository.util';

/**
 * @private
 * @sw-package discovery
 */
export type ScrollNavigationPosition = 'left' | 'right';

/**
 * `sidebar` renders the anchors as a floating list beside the content on large viewports, `flat` keeps the
 * horizontal bar above the content on every viewport.
 *
 * @private
 * @sw-package discovery
 */
export type ScrollNavigationMode = 'sidebar' | 'flat';

/**
 * @private
 * @sw-package discovery
 */
export type ScrollNavigationAnchor = {
    label: string;
};

/**
 * The `scrollNavigation` member of `content_layout.settings`: the page-wide switch, presentation and position,
 * plus the anchors keyed by the element id they are attached to.
 *
 * @private
 * @sw-package discovery
 */
export type ScrollNavigationSettings = {
    active: boolean;
    mode: ScrollNavigationMode;
    position: ScrollNavigationPosition;
    anchors: Record<string, ScrollNavigationAnchor>;
};

/**
 * @private
 * @sw-package discovery
 */
export const SCROLL_NAVIGATION_SETTINGS_KEY = 'scrollNavigation';

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function readAnchors(stored: unknown): Record<string, ScrollNavigationAnchor> {
    if (!isRecord(stored)) {
        return {};
    }

    return Object.entries(stored).reduce<Record<string, ScrollNavigationAnchor>>((anchors, [elementId, anchor]) => {
        if (!isRecord(anchor)) {
            return anchors;
        }

        anchors[elementId] = { label: typeof anchor.label === 'string' ? anchor.label : '' };

        return anchors;
    }, {});
}

/**
 * Reads the stored scroll navigation settings off a layout, normalising every member so callers never see a
 * half-written object.
 *
 * @private
 * @sw-package discovery
 */
export function readScrollNavigationSettings(layout: ContentLayoutEntity | null): ScrollNavigationSettings {
    const stored: unknown = layout?.settings?.[SCROLL_NAVIGATION_SETTINGS_KEY];

    if (!isRecord(stored)) {
        return { active: false, mode: 'sidebar', position: 'right', anchors: {} };
    }

    return {
        active: stored.active === true,
        mode: stored.mode === 'flat' ? 'flat' : 'sidebar',
        position: stored.position === 'left' ? 'left' : 'right',
        anchors: readAnchors(stored.anchors),
    };
}

function collectElementIds(elements: ContentElementNode[], ids: Set<string>): Set<string> {
    elements.forEach((element) => {
        ids.add(element.id);

        Object.values(element.slots ?? {}).forEach((children) => {
            collectElementIds(children, ids);
        });
    });

    return ids;
}

/**
 * Drops every anchor whose element is no longer part of the tree. Returns the same object when nothing
 * changed, so callers can skip a write.
 *
 * @private
 * @sw-package discovery
 */
export function pruneScrollNavigationAnchors(
    settings: ScrollNavigationSettings,
    elements: ContentElementNode[],
): ScrollNavigationSettings {
    const existingIds = collectElementIds(elements, new Set<string>());
    const staleIds = Object.keys(settings.anchors).filter((elementId) => !existingIds.has(elementId));

    if (staleIds.length === 0) {
        return settings;
    }

    const anchors = { ...settings.anchors };
    staleIds.forEach((elementId) => {
        delete anchors[elementId];
    });

    return { ...settings, anchors };
}

/**
 * Builds the `update-settings` payload that replaces the whole `scrollNavigation` member.
 *
 * @private
 * @sw-package discovery
 */
export function scrollNavigationSettingsPayload(settings: ScrollNavigationSettings): {
    settings: Record<string, unknown>;
} {
    return {
        settings: {
            [SCROLL_NAVIGATION_SETTINGS_KEY]: settings,
        },
    };
}
