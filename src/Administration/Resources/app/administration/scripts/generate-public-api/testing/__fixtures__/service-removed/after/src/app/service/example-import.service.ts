/**
 * @sw-package framework
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default class ExampleImportService {
    import(file: File): Promise<void> {
        return fetch('/import', { method: 'POST', body: file }).then(() => undefined);
    }
}
