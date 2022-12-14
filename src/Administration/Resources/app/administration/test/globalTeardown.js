/**
 * @package admin
 */

/* eslint-disable */
import fs from 'fs';
import path from 'path';
import xml2js from 'xml2js';

module.exports = async function testTeardown(globalConfig) {
    /**
     * This tearDown function for the unit test converts the source path
     * in the coberture file to the classes directly. This is needed because GitLab
     * has no support for the <sources> yet.
     */

    // run code only when cobertura coverage reporter exists
    if (!globalConfig.coverageReporters.includes('cobertura')) {
        return;
    }

    const coverageDirectory = globalConfig.coverageDirectory;
    const coberturaFileName = 'cobertura-coverage.xml';
    const cobertureFilePath = path.join(coverageDirectory, coberturaFileName);

    // stop execution if cobertura file was not written
    if (!fs.existsSync(cobertureFilePath)) {
        return;
    }

    const coberture = await readXmlAsObject(cobertureFilePath);

    const packages = coberture?.coverage?.packages ?? [];

    // add sourcePath to filename for each class
    packages.forEach((packagesEntry) => {
        // run through all sub packages
        (packagesEntry?.package ?? []).forEach(packageEntry => {
            // run through all classes
            (packageEntry?.classes ?? []).forEach((classesEntry) => {
                // run through all sub classes
                (classesEntry?.class ?? []).forEach(classEntry => {
                    // add full relative path from platform before filename
                    classEntry.$.filename = path.join('src/Administration/Resources/app/administration', classEntry.$.filename);
                })
            });
        });
    });

    // reset sources to default
    coberture.coverage.sources = [{ source: ['.'] }];

    writeObjectAsXml(coberture, cobertureFilePath);
};


async function readXmlAsObject(filePath) {
    const parser = new xml2js.Parser();

    if (!fs.existsSync(filePath)) {
        return {};
    }

    const file = fs.readFileSync(filePath);
    const parsedFile = await parser.parseStringPromise(file);

    return parsedFile;
}

async function writeObjectAsXml(object, filePath) {
    const builder = new xml2js.Builder();
    const xml = builder.buildObject(object);

    return fs.writeFileSync(filePath, xml);
}
