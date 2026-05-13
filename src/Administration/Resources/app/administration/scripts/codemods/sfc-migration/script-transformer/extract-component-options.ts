import type { ObjectLiteralExpression } from 'ts-morph';
import { SyntaxKind } from 'ts-morph';
import { getPropertyName } from './helpers';
import type { ComponentRegistration, EmitsDefinition } from './types';

export function extractPropsText(optionsObj: ObjectLiteralExpression): string | null {
    const prop = optionsObj.getProperty('props');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return null;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    return initializer?.getText() ?? null;
}

export function extractEmitsDefinition(optionsObj: ObjectLiteralExpression): EmitsDefinition {
    const prop = optionsObj.getProperty('emits');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { keys: [], objectText: null };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return {
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
    if (optionsObj.getProperty('render')) blockers.push('render function');

    return blockers;
}

export function extractPropNamesFromText(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('props');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return arrayInit
            .getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
    }

    const initializer = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    return (
        initializer
            ?.getProperties()
            .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
            .map((p) =>
                getPropertyName(
                    p.isKind(SyntaxKind.PropertyAssignment)
                        ? p.asKindOrThrow(SyntaxKind.PropertyAssignment)
                        : p.asKindOrThrow(SyntaxKind.MethodDeclaration),
                ),
            ) ?? []
    );
}
