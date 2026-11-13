/**
 * @sw-package inventory
 */

/** @private */
export function updateDocumentPositions(documents: EntityCollection<'product_document'>): void {
    documents.forEach((document, index) => {
        document.position = index;
    });
}
