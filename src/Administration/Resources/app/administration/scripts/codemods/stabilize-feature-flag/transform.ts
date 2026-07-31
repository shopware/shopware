/**
 * @sw-package framework
 */

import { Node, Project, SourceFile, ts } from 'ts-morph';
import type { ArrayLiteralExpression, CallExpression } from 'ts-morph';

function getActiveFeatureFlagsArray(callExpression: CallExpression): ArrayLiteralExpression | null {
    const testRegistration = callExpression.getExpression();
    if (!Node.isCallExpression(testRegistration)) {
        return null;
    }

    const activeFeatureFlags = testRegistration.getExpression();
    if (
        !Node.isPropertyAccessExpression(activeFeatureFlags) ||
        activeFeatureFlags.getExpression().getText() !== 'it' ||
        activeFeatureFlags.getName() !== 'activeFeatureFlags'
    ) {
        return null;
    }

    const [featureFlags] = testRegistration.getArguments();

    return Node.isArrayLiteralExpression(featureFlags) ? featureFlags : null;
}

/** @private */
export function stabilizeFeatureFlag(sourceFile: SourceFile, stabilizedFeatureFlag: string): boolean {
    let changed = false;

    sourceFile.getDescendantsOfKind(ts.SyntaxKind.CallExpression).forEach((callExpression) => {
        if (callExpression.wasForgotten()) {
            return;
        }

        const featureFlags = getActiveFeatureFlagsArray(callExpression);
        if (!featureFlags) {
            return;
        }

        const stabilizedFeatures = featureFlags.getElements().filter((featureFlag) => {
            return Node.isStringLiteral(featureFlag) && featureFlag.getLiteralValue() === stabilizedFeatureFlag;
        });

        if (stabilizedFeatures.length === 0) {
            return;
        }

        changed = true;
        stabilizedFeatures.reverse().forEach((stabilizedFeature) => featureFlags.removeElement(stabilizedFeature));

        if (featureFlags.getElements().length === 0) {
            const testRegistration = callExpression.getExpression();
            if (Node.isCallExpression(testRegistration)) {
                testRegistration.replaceWithText('it');
            }
        }
    });

    return changed;
}

/** @private */
export function transformSource(source: string, stabilizedFeatureFlag: string): string {
    const project = new Project({
        skipAddingFilesFromTsConfig: true,
        useInMemoryFileSystem: true,
    });
    const sourceFile = project.createSourceFile('test.spec.ts', source);

    stabilizeFeatureFlag(sourceFile, stabilizedFeatureFlag);

    return sourceFile.getFullText();
}
