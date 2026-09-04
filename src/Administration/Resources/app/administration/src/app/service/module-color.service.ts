/**
 * @sw-package framework
 */
import { warn } from 'src/core/service/utils/debug.utils';

/**
 * The color names a navigation entry may declare.
 *
 * A name is an *indication* of the navigation group, not a value: it maps to the
 * `--sw-module-color-<name>` custom property, which resolves to a different shade per UI color
 * mode (see `src/app/assets/scss/module-colors.scss`). Keep both lists in sync.
 *
 * @private
 */
export const MODULE_COLOR_NAMES = [
    'blue',
    'brand',
    'cyan',
    'emerald',
    'green',
    'orange',
    'pink',
    'pumpkin',
    'purple',
    'red',
    'slate',
    'yellow',
    'zinc',
] as const;

/**
 * @private
 */
export type ModuleColorName = (typeof MODULE_COLOR_NAMES)[number];

/**
 * Rendered when nothing in a navigation chain declares a color, and when a declared name is not one
 * we know. Same token the icons use while module colors are switched off, so an unresolvable color
 * is indistinguishable from "no color" instead of drawing attention to itself.
 *
 * @private
 */
export const NEUTRAL_MODULE_COLOR = 'var(--color-icon-primary-default)';

/**
 * The subset of a navigation entry this module needs. Structurally compatible with the navigation
 * entries of `ModuleManifest`, app modules, extension menu items and custom entity definitions.
 *
 * @private
 */
export interface ColorableNavigationEntry {
    id?: string;
    path?: string;
    parent?: string;
    color?: string;
}

/**
 * The subset of a `ModuleManifest` this module needs.
 *
 * @private
 */
export interface ColorableManifest {
    name?: string;
    color?: string;
    navigation?: ColorableNavigationEntry[];
    settingsItem?: unknown;
}

/**
 * A value is read as a color *name* when it is a bare lowercase word. Everything else — `#57d9a3`,
 * `var(--color-pink-500)`, `rgb(…)` — is a raw value from before the color names existed.
 */
const COLOR_NAME_PATTERN = /^[a-z][a-z-]*$/;

/**
 * Group the modules that are only reachable through the settings list belong to. They have no
 * navigation entry, so there is no chain to walk up.
 */
const SETTINGS_GROUP_ID = 'sw-settings';

/**
 * Sources already warned about, so a legacy raw value is reported once instead of on every render.
 */
const deprecationWarned = new Set<string>();

/**
 * @private
 */
export function isModuleColorName(value: unknown): value is ModuleColorName {
    return MODULE_COLOR_NAMES.includes(value as ModuleColorName);
}

/**
 * The custom property a color name renders through.
 *
 * @private
 */
export function moduleColorToken(name: ModuleColorName): string {
    return `var(--sw-module-color-${name})`;
}

/**
 * Turns one declared `color` into something renderable.
 *
 * - a known name becomes its token,
 * - an unknown name warns and falls back to the neutral icon color,
 * - a raw value is passed through and warns once per `source`, deprecated for removal in v6.9.0.
 *
 * `source` only names the declaration in those warnings — pass the id of the entry or module the
 * color came from.
 *
 * @private
 */
export function resolveDeclaredModuleColor(color: string | undefined, source: string): string | undefined {
    if (!color) {
        return undefined;
    }

    if (isModuleColorName(color)) {
        return moduleColorToken(color);
    }

    if (COLOR_NAME_PATTERN.test(color)) {
        warn(
            'ModuleColor',
            `"${source}" declares the unknown module color "${color}". Falling back to the neutral icon color.`,
            `Known names: ${MODULE_COLOR_NAMES.join(', ')}.`,
        );

        return NEUTRAL_MODULE_COLOR;
    }

    if (!deprecationWarned.has(source)) {
        deprecationWarned.add(source);

        warn(
            'ModuleColor',
            `"${source}" declares the raw color value "${color}".`,
            'Raw color values are deprecated and will be removed in v6.9.0.',
            `Declare a color name on the first-level navigation entry of the group instead: ${MODULE_COLOR_NAMES.join(', ')}.`,
        );
    }

    return color;
}

/**
 * Identity of a navigation entry. Entries are not required to carry an `id`, so the path is the
 * fallback — matching how the admin menu and the `adminMenu` store key their entries.
 */
function entryKey(entry: ColorableNavigationEntry): string | undefined {
    return entry.id ?? entry.path;
}

/**
 * Indexes entries by their identity, so a chain can be walked up by `parent`.
 */
function indexByKey<T extends ColorableNavigationEntry>(entries: T[]): Map<string, T> {
    const byKey = new Map<string, T>();

    entries.forEach((entry) => {
        const key = entryKey(entry);

        if (key !== undefined) {
            byKey.set(key, entry);
        }
    });

    return byKey;
}

/**
 * Walks up from `entry` and returns the closest entry that declares a color — the entry itself when
 * it declares one, otherwise the nearest ancestor that does. Returns `undefined` when nothing in the
 * chain declares a color, and stops walking when a parent is missing from the index or the chain
 * loops back onto itself.
 */
function closestDeclaringEntry<T extends ColorableNavigationEntry>(entry: T, byKey: Map<string, T>): T | undefined {
    const seen = new Set<string>();
    let current: T | undefined = entry;

    while (current) {
        if (current.color) {
            return current;
        }

        const key = entryKey(current);

        if (key !== undefined) {
            if (seen.has(key)) {
                return undefined;
            }

            seen.add(key);
        }

        current = current.parent ? byKey.get(current.parent) : undefined;
    }

    return undefined;
}

/**
 * The renderable color `entry` inherits, resolved against the given index.
 */
function inheritedColor<T extends ColorableNavigationEntry>(entry: T, byKey: Map<string, T>): string | undefined {
    const declaring = closestDeclaringEntry(entry, byKey);
    const source = (declaring && entryKey(declaring)) ?? entryKey(entry) ?? 'unknown navigation entry';

    return resolveDeclaredModuleColor(declaring?.color, source);
}

/**
 * Resolves the color of every navigation entry from the closest entry above it that declares one.
 *
 * The color indicates a navigation group, so in practice only the first-level entry of a group
 * declares it and everything below inherits — but a subtree may declare its own color and pass it
 * down, the way CSS inherits, instead of having the declaration silently ignored.
 *
 * Pass every entry the menu will render in one call: an entry can only inherit from an ancestor
 * that is in the same array.
 *
 * @private
 */
export function applyGroupColors<T extends ColorableNavigationEntry>(entries: T[]): T[] {
    const byKey = indexByKey(entries);

    return entries.map((entry) => ({ ...entry, color: inheritedColor(entry, byKey) }));
}

/**
 * Every navigation entry of every registered module, indexed by identity — so an entry can inherit
 * from a group that a different module registered.
 */
function registeredNavigationIndex(): Map<string, ColorableNavigationEntry> {
    const entries: ColorableNavigationEntry[] = [];

    Shopware.Module.getModuleRegistry().forEach((module) => {
        entries.push(...(module.manifest.navigation ?? []));
    });

    return indexByKey(entries);
}

/**
 * The group color of a whole module, for the places that hold a manifest rather than a navigation
 * entry — the page header and the search bar.
 *
 * A module is placed by the first of its navigation entries that resolves to a color. A module that
 * is only reachable through the settings list belongs to the settings group. A module in neither —
 * Sales Channels, Landing pages, Profile — falls back to the color it declares itself.
 *
 * @private
 */
export function resolveManifestModuleColor(manifest: ColorableManifest | undefined): string | undefined {
    if (!manifest) {
        return undefined;
    }

    const byKey = registeredNavigationIndex();

    for (const entry of manifest.navigation ?? []) {
        const color = inheritedColor(entry, byKey);

        if (color) {
            return color;
        }
    }

    const settingsGroup = manifest.settingsItem ? byKey.get(SETTINGS_GROUP_ID) : undefined;

    if (settingsGroup) {
        const color = inheritedColor(settingsGroup, byKey);

        if (color) {
            return color;
        }
    }

    return resolveDeclaredModuleColor(manifest.color, manifest.name ?? 'unknown module');
}
