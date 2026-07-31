/**
 * @sw-package framework
 */

import { existsSync } from 'node:fs';
import { resolve } from 'node:path';
import { Project } from 'ts-morph';
import { stabilizeFeatureFlag } from './transform';

const [
    ,
    ,
    stabilizedFeatureFlag,
    targetDirectory = 'src',
] = process.argv;

if (!stabilizedFeatureFlag) {
    throw new Error('Please provide the feature flag to stabilize.');
}

const resolvedTargetDirectory = resolve(targetDirectory);
if (!existsSync(resolvedTargetDirectory)) {
    throw new Error(`Target directory does not exist: ${resolvedTargetDirectory}`);
}

const project = new Project({
    skipAddingFilesFromTsConfig: true,
});

const sourceFiles = project.addSourceFilesAtPaths([
    `${resolvedTargetDirectory}/**/*.spec.{js,ts}`,
    `!${resolvedTargetDirectory}/**/*.spec.vue2.{js,ts}`,
]);

let changedFiles = 0;

sourceFiles.forEach((sourceFile) => {
    if (!stabilizeFeatureFlag(sourceFile, stabilizedFeatureFlag)) {
        return;
    }

    sourceFile.saveSync();
    changedFiles += 1;
    console.info(`Updated ${sourceFile.getFilePath()}`);
});

console.info(`Stabilized ${stabilizedFeatureFlag} in ${changedFiles} test file(s).`);
