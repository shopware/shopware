/**
 * @sw-package framework
 */

import * as fs from 'fs';
import * as path from 'path';
import { globSync } from 'glob';
import { missingTests, positionIdentifiers, dataSetIds } from './baseline';
import packageJson from '../../package.json';
import blocksList from '../../blocks-list.json';
import { extractBlocks } from '../../scripts/generate-block-list/extract-blocks';

// eslint-disable-next-line no-undef
const allFiles = globSync(path.join(adminPath, 'src/**/*.*'));
const testAbleFiles = allFiles.filter((file) => {
    return file.match(/^.*(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts)$/);
});
const templateFiles = allFiles.filter((file) => {
    return file.match(/^.*\.html\.twig$/);
});

describe('Administration meta tests', () => {
    describe('check for test files', () => {
        it.skip.each(testAbleFiles)('should have a spec file for "%s"', (file) => {
            // Match 0 holds the whole file path
            // Match 1 holds the last folder name e.g. "adapter"
            // Match 2 holds the file name e.g. "view.adapter.ts"
            // Match 3 holds the file name without extension e.g. "view.adapter"
            // Match 4 holds the file extension e.g. "ts"
            const regex = /^.*\/(.*)\/((.*)\.(js|ts))$/;

            const [
                whole,
                lastFolder,
                fileName,
                fileNameWithoutExtension,
                extension,
            ] = file.match(regex);

            const isInBaseLine =
                missingTests.includes(fileName) ||
                missingTests.includes(`${lastFolder}/${fileName}`) ||
                missingTests.some((filePath) => {
                    return whole.includes(filePath);
                });

            const specFile = whole.replace(fileName, `${fileNameWithoutExtension}.spec.js`);
            const specFileExists = fs.existsSync(specFile);

            const specTsFile = whole.replace(fileName, `${fileNameWithoutExtension}.spec.ts`);
            const specTsFileExists = fs.existsSync(specTsFile);

            const specFileWithFolderName = whole.replace(fileName, `${lastFolder}.spec.js`);
            const specFileWithFolderNameExists = fs.existsSync(specFileWithFolderName);

            const specTsFileWithFolderName = whole.replace(fileName, `${lastFolder}.spec.ts`);
            const specTsFileWithFolderNameExists = fs.existsSync(specTsFileWithFolderName);

            let specFileAlternativeExtension = '';
            let specFileWithFolderNameAlternativeExtension = '';
            if (extension === 'js') {
                specFileAlternativeExtension = specFile.replace('.js', '.ts');
                specFileWithFolderNameAlternativeExtension = specFileWithFolderName.replace('.js', '.ts');
            } else {
                specFileAlternativeExtension = specFile.replace('.ts', '.js');
                specFileWithFolderNameAlternativeExtension = specFileWithFolderName.replace('.ts', '.js');
            }
            const specFileAlternativeExtensionExists = fs.existsSync(specFileAlternativeExtension);
            const specFileWithFolderNameAlternativeExtensionExists = fs.existsSync(
                specFileWithFolderNameAlternativeExtension,
            );

            const fileIsTested =
                isInBaseLine ||
                specFileExists ||
                specTsFileExists ||
                specFileWithFolderNameExists ||
                specTsFileWithFolderNameExists ||
                specFileAlternativeExtensionExists ||
                specFileWithFolderNameAlternativeExtensionExists;

            // check if spec file exists but file is still in baseline
            expect(
                isInBaseLine &&
                    (specFileExists ||
                        specFileWithFolderNameExists ||
                        specFileAlternativeExtensionExists ||
                        specFileWithFolderNameAlternativeExtensionExists),
            ).toBe(false);

            expect(fileIsTested).toBeTruthy();
        });

        it.skip.each(missingTests)('should have an corresponding src file for entry in baseline: "%s"', (file) => {
            expect(testAbleFiles.some((tFile) => tFile.includes(file))).toBe(true);
        });
    });

    describe('check package.json', () => {
        it.skip('should have engine information in package.json', () => {
            expect(typeof packageJson).toBe('object');
            expect(packageJson.hasOwnProperty('engines')).toBe(true);
            expect(packageJson.engines.hasOwnProperty('node')).toBe(true);
            expect(packageJson.engines.node).toBe('^20.0.0 || ^21.0.0 || ^22.0.0 || ^23.0.0');
            expect(packageJson.engines.hasOwnProperty('npm')).toBe(true);
            expect(packageJson.engines.npm).toBe('>=10.0.0');
        });
    });

    describe('check extension sdk public api', () => {
        it.skip('should not break position identifiers', () => {
            const result = [];
            templateFiles.forEach((file) => {
                const fileContent = fs.readFileSync(file, {
                    encoding: 'utf-8',
                });
                if (!fileContent.includes('position-identifier="')) {
                    return;
                }

                // Find all position identifiers in the file and add them to the result
                [...fileContent.matchAll(/position-identifier="(.*)"/gm)]
                    .map((match) => match[1])
                    .forEach((match) => {
                        result.push(match);
                    });
            });

            const missingPositionIdentifiers = positionIdentifiers.filter((pi) => !result.includes(pi));
            expect(
                missingPositionIdentifiers,
                `Breaking change detected! Previously registered position identifiers are missing: \n${missingPositionIdentifiers.join(', ')}`,
            ).toHaveLength(0);

            // If we reach this segment we know no identifiers have been removed. Inform the dev that they need to update the identifiers
            expect(
                result,
                'Seems like you added new position identifiers. You need to run "composer run admin:generate-position-identifier-list" to update the position identifier list :)!',
            ).toHaveLength(positionIdentifiers.length);
        });

        it.skip('should not break data sets', () => {
            const result = [];
            testAbleFiles.forEach((file) => {
                const fileContent = fs.readFileSync(file, {
                    encoding: 'utf-8',
                });
                if (!fileContent.includes('.publishData(')) {
                    return;
                }

                // Find all data set ids in the file and add them to the result
                [
                    ...fileContent.matchAll(/\.publishData\(\{[^}]*?\bid\s*:\s*['"]([^'"]+)['"]/gm),
                ]
                    .map((match) => match[1])
                    .forEach((match) => {
                        result.push(match);
                    });
            });

            const missingDataSetIds = dataSetIds.filter((pi) => !result.includes(pi));
            expect(
                missingDataSetIds,
                `Breaking change detected! Previously registered data sets are missing: \n${missingDataSetIds.join(', ')}`,
            ).toHaveLength(0);

            // If we reach this segment we know no data sets have been removed. Inform the dev that they need to update the data sets
            expect(
                result,
                'Seems like you added new data sets. You need to run "composer run admin:generate-data-set-list" to update the position identifier list :)!',
            ).toHaveLength(dataSetIds.length);
        });

        it.skip('should not remove existing blocks', () => {
            const blocks = extractBlocks(templateFiles);
            const removedBlocks = blocksList.filter((block) => !blocks.includes(block));

            expect(
                removedBlocks,
                `Breaking change detected! Previously registered blocks are missing: \n${removedBlocks.join(', ')}`,
            ).toHaveLength(0);
        });

        it.skip('should have new blocks in the blocks list', () => {
            const blocks = extractBlocks(templateFiles);
            const newBlocks = blocks.filter((block) => !blocksList.includes(block));

            expect(
                newBlocks,
                `New blocks have been added. Please run 'generate-block-list' script to add them to the blocks list: \n${newBlocks.join(', ')}`,
            ).toHaveLength(0);
        });
    });
});
