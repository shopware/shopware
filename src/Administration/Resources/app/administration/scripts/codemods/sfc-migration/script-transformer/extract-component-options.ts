import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { getPropertyName } from './helpers';
import type { ComponentRegistration, EmitsDefinition } from './types';

export function extractPropsText(optionsObj: ObjectLiteralExpression): string | null {
    const prop = optionsObj.getProperty('props');
    // TODO: Silent ignore: shorthand/non-property `props` declarations are
    // treated as absent, which can leave `this.<prop>` unresolved without
    // reporting that prop extraction failed.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return null;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    return initializer?.getText() ?? null;
}

export function extractEmitsDefinition(optionsObj: ObjectLiteralExpression): EmitsDefinition {
    const prop = optionsObj.getProperty('emits');
    // TODO: Silent ignore: shorthand/non-property `emits` declarations are
    // treated as absent and may be replaced by inferred emits.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { keys: [], objectText: null };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return {
            // TODO: Silent ignore: spread or computed array entries are filtered
            // out instead of making `emits` partially migratable.
            keys: arrayInit
                .getElements()
                .filter((el) => el.isKind(SyntaxKind.StringLiteral))
                .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()),
            objectText: null,
        };
    }

    const objInit = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (objInit) {
        return {
            // TODO: Silent ignore: object-form emits spreads and computed keys
            // are ignored for key extraction while the full object can still be
            // passed through to defineEmits.
            keys: objInit
                .getProperties()
                .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
                .map((p) =>
                    p.isKind(SyntaxKind.MethodDeclaration)
                        ? p.asKindOrThrow(SyntaxKind.MethodDeclaration).getName()
                        : p.asKindOrThrow(SyntaxKind.PropertyAssignment).getName(),
                ),
            objectText: objInit.getText(),
        };
    }

    return { keys: [], objectText: null };
}

export function extractInheritAttrs(optionsObj: ObjectLiteralExpression): boolean {
    const prop = optionsObj.getProperty('inheritAttrs');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return true;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    // TODO: Silent ignore: dynamic inheritAttrs expressions are collapsed to
    // `true`, so the generated component can silently change attr inheritance.
    return initializer?.getText() !== 'false';
}

export function detectBlockers(optionsObj: ObjectLiteralExpression, registration: ComponentRegistration): string[] {
    const blockers: string[] = [];

    if (registration.isExtend) {
        blockers.push(
            registration.parentComponentName ? `extends (parent: ${registration.parentComponentName})` : 'extends',
        );
    }
    if (optionsObj.getProperty('mixins')) blockers.push('mixins');
    // TODO: Silent ignore: computed `render` keys, root option spreads, and
    // unsupported runtime options are not checked here, so they can be dropped
    // while the result is still marked fully migratable.
    if (optionsObj.getProperty('render')) blockers.push('render function');

    return blockers;
}

export function extractPropNamesFromText(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('props');
    // TODO: Silent ignore: shorthand/non-property `props` declarations return
    // no prop names instead of reporting that prop extraction was unsupported.
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        // TODO: Silent ignore: non-string prop array entries such as spreads are
        // filtered out, so later `this.<prop>` rewriting can miss hidden props.
        return arrayInit
            .getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
    }

    const initializer = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    return (
        initializer
            ?.getProperties()
            // TODO: Silent ignore: prop object spreads and computed property
            // names are filtered out instead of making the component partially
            // migratable.
            .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
            .map((p) =>
                getPropertyName(
                    p.isKind(SyntaxKind.PropertyAssignment)
                        ? p.asKindOrThrow(SyntaxKind.PropertyAssignment)
                        : p.asKindOrThrow(SyntaxKind.MethodDeclaration),
                ),
            // TODO: Silent ignore: non-literal props initializers return an
            // empty prop-name set, which hides unsupported prop extraction from
            // the migration status.
            ) ?? []
    );
}
