/**
 * @package admin
 */

import * as fs from 'fs';
import * as path from 'path';
import baseline from './baseline';
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

const rootPath = 'src';
// @ts-expect-error
// eslint-disable-next-line no-undef
const testAbleFiles = getAllFiles(path.join(adminPath, rootPath)).filter(file => {
    return file.match(/^.*(?<!\.spec|vue2)(?<!\/acl\/index)(?<!\.d)\.(js|ts)$/);
});

describe('Administration meta tests', () => {
    it.each(testAbleFiles)('should have a spec file for "%s"', (file) => {
        // Match 0 holds the whole file path
        // Match 1 holds the last folder name e.g. "adapter"
        // Match 2 holds the file name e.g. "view.adapter.ts"
        // Match 3 holds the file name without extension e.g. "view.adapter"
        // Match 4 holds the file extension e.g. "ts"
        const regex = /^.*\/(.*)\/((.*)\.(js|ts))$/;

        const [whole, lastFolder, fileName, fileNameWithoutExtension, extension] = file.match(regex);

        const isInBaseLine = baseline.includes(fileName) ||
            baseline.includes(`${lastFolder}/${fileName}`) ||
            baseline.some(filePath => {
                return whole.includes(filePath);
            });

        const specFile = whole.replace(fileName, `${fileNameWithoutExtension}.spec.js`);
        const specFileExists = fs.existsSync(specFile);

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
        const specFileWithFolderNameAlternativeExtensionExists = fs.existsSync(specFileWithFolderNameAlternativeExtension);

        const fileIsTested = isInBaseLine || specFileExists || specFileWithFolderNameExists || specFileAlternativeExtensionExists || specFileWithFolderNameAlternativeExtensionExists;

        // check if spec file exists but file is still in baseline
        expect(
            isInBaseLine && (
                specFileExists ||
                specFileWithFolderNameExists ||
                specFileAlternativeExtensionExists ||
                specFileWithFolderNameAlternativeExtensionExists
            ),
        ).toBe(false);

        expect(fileIsTested).toBeTruthy();
    });

    it.each(baseline)('should have an corresponding src file for entry in baseline: "%s"', (file) => {
        expect(testAbleFiles.some(tFile => tFile.includes(file))).toBe(true);
    });

    it('should have engine information in package.json', () => {
        expect(typeof packageJson).toBe('object');
        expect(packageJson.hasOwnProperty('engines')).toBe(true);
        expect(packageJson.engines.hasOwnProperty('node')).toBe(true);
        expect(packageJson.engines.node).toBe('^20.0.0');
        expect(packageJson.engines.hasOwnProperty('npm')).toBe(true);
        expect(packageJson.engines.npm).toBe('>=10.0.0');
    });
});
