/* eslint-disable */
import fs from 'fs';
import path from 'path';
import xml2js from 'xml2js';

/**
 * This teardown function for the unit test converts the source path
 * in the cobertura file to the classes directly. This is needed because GitLab
 * has no support for the <sources> yet.
 *
 * @package storefront
 */
module.exports = function testTeardown(globalConfig) {

    // run code only when cobertura coverage reporter exists
    if (!globalConfig.coverageReporters.includes('cobertura')) {
        return;
    }

    const coverageDirectory = globalConfig.coverageDirectory;
    const coberturaFileName = 'cobertura-coverage.xml';
    const coberturaFilePath = path.join(coverageDirectory, coberturaFileName);

    // stop execution if cobertura file was not written
    if (!fs.existsSync(coberturaFilePath)) {
        return;
    }

    readXmlAsObject(coberturaFilePath, (err, cobertura) => {
        if (err !== null) {
            console.error('[globalTeardown.js] Error while parsing XML for coverage.', err);
            return;
        }

        const packages = cobertura && cobertura.coverage && cobertura.coverage.packages != null ? cobertura.coverage.packages : [];

        // add sourcePath to filename for each class
        packages.forEach((packagesEntry) => {
            // run through all sub packages
            (packagesEntry && packagesEntry.package != null ? packagesEntry.package : []).forEach(packageEntry => {
                // run through all classes
                (packageEntry && packageEntry.classes != null ? packageEntry.classes : []).forEach((classesEntry) => {
                    // run through all sub classes
                    (classesEntry && classesEntry.class != null ? classesEntry.class : []).forEach(classEntry => {
                        // add full relative path from platform before filename
                        classEntry.$.filename = path.join('src/Storefront/Resources/app/storefront', classEntry.$.filename);
                    })
                });
            });
        });

        // reset sources to default
        cobertura.coverage.sources = [{ source: ['.'] }];

        writeObjectAsXml(cobertura, coberturaFilePath);
    });
};

function readXmlAsObject(filePath, cb) {
    const parser = new xml2js.Parser();

    if (!fs.existsSync(filePath)) {
        return {};
    }

    const file = fs.readFileSync(filePath);

    parser.parseString(file, (err, result) => {
        cb(err, result);
    });
}

function writeObjectAsXml(object, filePath) {
    const builder = new xml2js.Builder();
    const xml = builder.buildObject(object);

    return fs.writeFileSync(filePath, xml);
}
