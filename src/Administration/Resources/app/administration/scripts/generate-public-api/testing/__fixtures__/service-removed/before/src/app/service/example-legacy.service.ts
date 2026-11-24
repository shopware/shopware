/**
 * @sw-package framework
 */

/**
 * @deprecated tag:v6.7.0 - Will be removed, use exampleExportService instead.
 */
export default class ExampleLegacyService {
    exportAll(): Promise<Blob> {
        return fetch('/export').then((response) => response.blob());
    }
}
