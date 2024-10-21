/**
 * @package admin
 */

import * as fs from 'fs';
import * as path from 'path';
import { missingTests, positionIdentifiers, dataSetIds } from './baseline';
import packageJson from '../../package.json';

const getAllFiles = (dirPath, arrayOfFiles = null) => {
    const files = fs.readdirSync(dirPath);

    arrayOfFiles = arrayOfFiles || [];

    files.forEach((file) => {
        if (fs.statSync(`${dirPath}/${file}`).isDirectory()) {
            arrayOfFiles = getAllFiles(`${dirPath}/${file}`, arrayOfFiles);
        } else {
            arrayOfFiles.push(path.join(dirPath, '/', file));
        }
    });

    return arrayOfFiles;
};

// eslint-disable-next-line no-undef
const allFiles = getAllFiles(path.join(adminPath, 'src'));
const testAbleFiles = allFiles.filter((file) => {
    return file.match(/^.*(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts)$/);
});
const templateFiles = allFiles.filter((file) => {
    return file.match(/^.*\.html\.twig$/);
});

describe('Administration meta tests', () => {
    describe('check for test files', () => {
        it.each(testAbleFiles)('should have a spec file for "%s"', (file) => {
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

        it.each(missingTests)('should have an corresponding src file for entry in baseline: "%s"', (file) => {
            expect(testAbleFiles.some((tFile) => tFile.includes(file))).toBe(true);
        });
    });

    describe('check package.json', () => {
        it('should have engine information in package.json', () => {
            expect(typeof packageJson).toBe('object');
            expect(packageJson.hasOwnProperty('engines')).toBe(true);
            expect(packageJson.engines.hasOwnProperty('node')).toBe(true);
            expect(packageJson.engines.node).toBe('^20.0.0');
            expect(packageJson.engines.hasOwnProperty('npm')).toBe(true);
            expect(packageJson.engines.npm).toBe('>=10.0.0');
        });
    });

    describe('check extension sdk public api', () => {
        it('should not break position identifiers', () => {
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

        it('should not break data sets', () => {
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
    });
});
