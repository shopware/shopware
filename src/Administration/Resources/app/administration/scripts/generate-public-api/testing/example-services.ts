/**
 * @sw-package framework
 *
 * Small source trees for the specs about services: `exampleService`, which `src/app/main.ts` registers with a
 * provider, given by the file that declares it.
 */
import type { SourceTree } from '../source/source-tree';
import { treeOf } from './example-components';

/** @private */
export const MAIN = 'src/app/main.ts';

/** @private */
export const SERVICE_FILE = 'src/app/service/example.service.ts';

/** @private */
export const SERVICE_REPORT = 'app/service/example.service.api.md';

/**
 * @private
 *
 * `main.ts` with the import of the example service and the statements that register services.
 */
export function mainFile(...registrations: string[]): string {
    return ["import ExampleService from 'src/app/service/example.service';", '', ...registrations].join('\n');
}

/** @private */
export const REGISTRATION = "Shopware.Application.addServiceProvider('exampleService', () => new ExampleService());";

/**
 * @private
 *
 * A tree that registers `exampleService` as an instance of the default export of `service`. `files` adds to
 * the tree or replaces a file of it.
 */
export function serviceTree(service: string, files: Record<string, string> = {}): SourceTree {
    return treeOf({ [MAIN]: mainFile(REGISTRATION), [SERVICE_FILE]: service, ...files });
}

/**
 * @private
 *
 * The same for a service that is an instance of a class with this body.
 */
export function classTree(body: string, files: Record<string, string> = {}): SourceTree {
    return serviceTree(`export default class ExampleService {\n${body}\n}`, files);
}
