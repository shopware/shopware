/**
 * @sw-package framework
 * @private
 *
 * Makes the data scope of a Twig-owned block survive an override that was written for a native owner.
 *
 * A native base merges an override's non-public bindings into its own state under the `__swOverride`
 * key, and the generated block content destructures them straight out of the slot scope:
 *
 *     ({ __swOverride: { [namespace]: { clickCount } } }) => ...
 *
 * A Twig component never runs the extendable setup, so that key is simply absent — and destructuring it
 * throws, taking down every page that renders the component. The block content itself is usually fine;
 * only its state is missing. So the scope answers `__swOverride` with a proxy that yields an empty
 * object for any namespace: the bindings destructure to `undefined`, the block renders, and the author
 * gets one clear warning instead of a `TypeError` with no obvious cause.
 */

const OVERRIDE_STATE_KEY = '__swOverride';

/**
 * Answers every namespace lookup with an empty object, so a nested destructuring pattern resolves to
 * `undefined` bindings rather than throwing.
 */
const emptyOverrideState = new Proxy(
    {},
    {
        get: () => ({}),
    },
);

const warnedBlockNames = new Set<string>();

/**
 * Warns once per block that an override's own state cannot reach a Twig component.
 */
function warnAboutMissingOverrideState(blockName: string): void {
    if (warnedBlockNames.has(blockName)) {
        return;
    }

    warnedBlockNames.add(blockName);

    console.warn(
        `[sw-block] An override of "${blockName}" reads its own setup state, but the block is owned by a ` +
            'Twig component, which never runs the extendable setup. The block content renders with those ' +
            'bindings undefined. Migrate the owning component to a native SFC, or keep the override content ' +
            'free of override-local state.',
    );
}

/**
 * Wraps the data scope of a block emitted from a Twig template.
 *
 * Use it in `sw-block` before handing `props.data` to the block slots.
 *
 * @example
 * const scope = withTwigOwnerDataScope(props.data, props.name);
 *
 * @private
 */
export default function withTwigOwnerDataScope<TData extends object | null>(data: TData, blockName: string): TData {
    if (!data) {
        return data;
    }

    return new Proxy(data, {
        get(target, key) {
            if (key === OVERRIDE_STATE_KEY && !Reflect.has(target, key)) {
                warnAboutMissingOverrideState(blockName);

                return emptyOverrideState;
            }

            // No receiver forwarding: a getter on the real scope must resolve `this` against the scope
            // itself, not against this proxy.
            return Reflect.get(target, key) as unknown;
        },
    });
}

/**
 * Clears the one-warning-per-block memory.
 *
 * Use it in tests; the administration itself keeps warning state for the whole session.
 *
 * @example
 * afterEach(() => resetTwigOwnerDataScopeWarnings());
 *
 * @private
 */
export function resetTwigOwnerDataScopeWarnings(): void {
    warnedBlockNames.clear();
}
