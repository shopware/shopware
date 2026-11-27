/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ExampleExportService {
    export(entityName: string): Promise<Blob> {
        return fetch(`/export/${entityName}`).then((response) => response.blob());
    }
}
