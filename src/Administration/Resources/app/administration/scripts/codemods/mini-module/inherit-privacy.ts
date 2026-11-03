/**
 * @sw-package framework
 * @private
 */

import { isDocumentedPrivate, isDocumentedPublic } from './mark-export';
import type { Shape } from './render';

/**
 * Which declaration a file holds for a registered component that is already a mini-module, or null:
 * `public` for the `definePublicComponent()` of an index.ts, `private` for the `definePrivateComponent()`
 * of a private.ts next to a register.ts. A nested private module has no register.ts and no global name.
 *
 * @example
 * readDeclarationKind('src/app/component/base/sw-collapse/private.ts', "definePrivateComponent({ name: 'sw-collapse' })", true);
 * // → 'private'
 */
export function readDeclarationKind(file: string, source: string, hasRegister: boolean): 'public' | 'private' | null {
    if (file.endsWith('/index.ts') && source.includes('definePublicComponent(')) {
        return 'public';
    }

    if (file.endsWith('/private.ts') && source.includes('definePrivateComponent(') && hasRegister) {
        return 'private';
    }

    return null;
}

/**
 * The shape of a component that extends another one. It is private when its registration or its doc comment
 * says so, and when it inherits from a private component, because an override of it reaches what it inherits.
 * Only a doc comment that says `@public` keeps such a component public.
 *
 * @example
 * readExtendShape({ base: 'sw-condition-base', isRegistrationPrivate: false, componentSource: 'export default {};', privateBase: 'sw-condition-base' });
 * // → { kind: 'extend', base: 'sw-condition-base', isPrivate: true, privateBase: 'sw-condition-base' }
 */
export function readExtendShape({
    base,
    isRegistrationPrivate,
    componentSource,
    privateBase,
}: {
    base: string;
    isRegistrationPrivate: boolean;
    componentSource: string;
    privateBase: string | null;
}): Extract<Shape, { kind: 'extend' }> {
    const isPrivate =
        isRegistrationPrivate ||
        isDocumentedPrivate(componentSource) ||
        (privateBase !== null && !isDocumentedPublic(componentSource));

    return privateBase === null ? { kind: 'extend', base, isPrivate } : { kind: 'extend', base, isPrivate, privateBase };
}
