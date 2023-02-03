/**
 * @package admin
 */

import * as fs from 'fs';
import * as path from 'path';
import baseline from './baseline';
import packageJson from '../../package.json';


const getAllFiles = (dirPath: string, arrayOfFiles: Array<string> = null): Array<string> => {
    const files = fs.readdirSync(dirPath);

    arrayOfFiles = arrayOfFiles || [];

    files.forEach((file): void => {
        if (fs.statSync(`${dirPath}/${file}`).isDirectory()) {
            arrayOfFiles = getAllFiles(`${dirPath}/${file}`, arrayOfFiles);
        } else {
            arrayOfFiles.push(path.join(dirPath, '/', file));
        }
    });

    return arrayOfFiles;
};

const rootPath = 'src';
// @ts-expect-error
// eslint-disable-next-line no-undef
const testAbleFiles = getAllFiles(path.join(adminPath, rootPath)).filter(file => {
    return file.match(/^.*(?<!\.spec)(?<!\/acl\/index)(?<!\.d)\.(js|ts)$/);
});

describe('Administration meta tests', () => {
    it.each(testAbleFiles)('should have a spec file for %s', (file) => {
        // Match 0 holds the whole file path
        // Match 1 holds the last folder name e.g. "adapter"
        // Match 2 holds the file name e.g. "view.adapter.ts"
        // Match 3 holds the file name without extension e.g. "view.adapter"
        // Match 4 holds the file extension e.g. "ts"
        const regex = /^.*\/(.*)\/((.*)\.(js|ts))$/;

        const [whole, lastFolder, fileName, fileNameWithoutExtension, extension] = file.match(regex);
        if (baseline.includes(fileName) || baseline.includes(`${lastFolder}/${fileName}`)) {
            expect(true).toBe(true);

            return;
        }

        const specFile = whole.replace(fileName, `${fileNameWithoutExtension}.spec.${extension}`);
        const specFileExists = fs.existsSync(specFile);

        const specFileWithFolderName = whole.replace(fileName, `${lastFolder}.spec.${extension}`);
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
        const specFileWithFolderNameAlternativeExtensionExists = fs.existsSync(specFileWithFolderNameAlternativeExtension);

        const fileIsTested = specFileExists || specFileWithFolderNameExists || specFileAlternativeExtensionExists || specFileWithFolderNameAlternativeExtensionExists;

        expect(fileIsTested).toBeTruthy();
    });

    it('should have engine information in package.json', () => {
        expect(typeof packageJson).toBe('object');
        expect(packageJson.hasOwnProperty('engines')).toBe(true);
        expect(packageJson.engines.hasOwnProperty('node')).toBe(true);
        expect(packageJson.engines.node).toBe('^18.0.0');
        expect(packageJson.engines.hasOwnProperty('npm')).toBe(true);
        expect(packageJson.engines.npm).toBe('^8.0.0 || ^9.0.0');
    });
});
