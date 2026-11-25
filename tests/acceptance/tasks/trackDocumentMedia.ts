import type { FixtureTypes } from '@fixtures/AcceptanceTest';
import { getMediaId } from '@fixtures/AcceptanceTest';

/**
 * Registers a UI-uploaded document file with the TestDataService so cleanup removes it.
 * A document's media is not cascade-deleted with the product, and a still-referenced file cannot be
 * deleted, so deleting it in a task's own teardown (before the product is gone) is unreliable.
 * Registering it has the TestDataService delete it after the product, in the right order.
 * No-op when the upload was rejected and created no media.
 */
export async function trackUploadedDocumentMedia(
    TestDataService: FixtureTypes['TestDataService'],
    AdminApiContext: FixtureTypes['AdminApiContext'],
    documentName: string,
): Promise<void> {
    try {
        const mediaId = await getMediaId(documentName, AdminApiContext);
        if (mediaId) {
            TestDataService.addCreatedRecord('media', mediaId);
        }
    } catch {
        // Rejected upload: no media was created, nothing to track.
    }
}
