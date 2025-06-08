import entitySchema from "../../test/_mocks_/entity-schema.json";
import path from 'path';
import { EntitySchemaConverter } from "./entity-schema-converter";

const converter = new EntitySchemaConverter();

// @ts-ignore
converter.convert(entitySchema, path.join(__dirname, '../../src/entity-schema-definition.d.ts'));
