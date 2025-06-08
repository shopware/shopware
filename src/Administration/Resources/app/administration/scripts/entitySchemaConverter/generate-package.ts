import fs from 'fs';
import path from 'path';
import entitySchema from "../../test/_mocks_/entity-schema.json";
import { EntitySchemaConverter } from "./entity-schema-converter";

async function main() {
    const gitCommitTag = process.env.CI_COMMIT_TAG;

    if (!gitCommitTag || typeof gitCommitTag !== 'string') {
        throw new Error('No git commit tag found. Please set the CI_COMMIT_TAG environment variable.');
    }

    const converter = new EntitySchemaConverter();
    const packageName = '@shopware-ag/entity-schema-types'
    const folderPackagePath = path.join(__dirname, '../../entity-schema-types');
    const definitionFileName = 'entity-schema-definition.d.ts';
    const packageVersion = gitCommitTag.replace('v6.', '');

    // Delete package folder if exists
    if (fs.existsSync(folderPackagePath)) {
        // @ts-ignore
        fs.rmSync(folderPackagePath, { recursive: true });
    }

    // Create new empty package folder
    fs.mkdirSync(folderPackagePath);

    // Create package.json
    fs.writeFileSync(path.join(folderPackagePath, 'package.json'), JSON.stringify({
        name: packageName,
        version: packageVersion,
        description: 'TypeScript definition file for the corresponding entity schema',
        license: 'MIT',
        types: definitionFileName,
    }, null, 4));

    // @ts-ignore
    converter.convert(entitySchema, path.join(folderPackagePath, definitionFileName));
}

main();
