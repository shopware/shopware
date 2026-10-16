import type { Locator } from '@playwright/test';
import type { expect, FixtureTypes } from '@fixtures/AcceptanceTest';

export type DocumentTypes =
    | 'invoice'
    | 'credit_note'
    | 'delivery_note'
    | 'cancellation_invoice'
    | 'zugferd_embedded_invoice'
    | 'zugferd_embedded_cancellation_invoice'
    | 'zugferd_embedded_credit_note';

export interface DocumentOptions {
    orderId: string;
    type: DocumentTypes;
    referencedDocumentId?: string;
}

interface MaskRegion {
    x: number;
    y: number;
    width: number;
    height: number;
}

const baseInvoiceMasks: MaskRegion[] = [
    { x: 393, y: 321, width: 45, height: 20 },
    { x: 830, y: 230, width: 145, height: 75 },
];

const baseCancellationInvoiceMasks: MaskRegion[] = [
    { x: 428, y: 321, width: 45, height: 20 },
    { x: 542, y: 321, width: 45, height: 20 },
    { x: 820, y: 210, width: 145, height: 100 },
];

const baseCreditNoteMasks: MaskRegion[] = [
    { x: 419, y: 321, width: 70, height: 20 },
    { x: 558, y: 321, width: 125, height: 20 },
    { x: 830, y: 228, width: 145, height: 75 },
];

const documentMasks: Record<DocumentTypes, MaskRegion[]> = {
    invoice: [
        ...baseInvoiceMasks,
        { x: 110, y: 18, width: 45, height: 20 },
    ],
    zugferd_embedded_invoice: [
        ...baseInvoiceMasks,
        { x: 182, y: 18, width: 115, height: 20 },
    ],
    cancellation_invoice: [
        ...baseCancellationInvoiceMasks,
        { x: 144, y: 18, width: 45, height: 20 },
        { x: 245, y: 18, width: 45, height: 20 },
    ],
    zugferd_embedded_cancellation_invoice: [
        ...baseCancellationInvoiceMasks,
        { x: 182, y: 18, width: 115, height: 20 },
    ],
    credit_note: [
        ...baseCreditNoteMasks,
        { x: 130, y: 18, width: 70, height: 20 },
    ],
    zugferd_embedded_credit_note: [
        ...baseCreditNoteMasks,
        { x: 182, y: 18, width: 115, height: 20 },
    ],
    delivery_note: [
        { x: 145, y: 18, width: 45, height: 20 },
        { x: 238, y: 18, width: 45, height: 20 },
        { x: 434, y: 321, width: 45, height: 20 },
        { x: 539, y: 321, width: 130, height: 20 },
        { x: 830, y: 210, width: 145, height: 100 },
    ],
};

export async function screenshotDocument(
    name: string,
    triggerLocator: Locator,
    expects: typeof expect,
    documentType: DocumentTypes,
    additionalMasks: MaskRegion[] = [],
) {
    const page = triggerLocator.page();

    const maskRegions = [
        ...documentMasks[documentType],
        ...additionalMasks,
    ];

    const [pdfPage] = await Promise.all([
        page.context().waitForEvent('page'),
        triggerLocator.click(),
    ]);

    await pdfPage.setViewportSize({ width: 1000, height: 1000 });

    // eslint-disable-next-line playwright/no-networkidle
    await pdfPage.waitForLoadState('networkidle');

    if (maskRegions?.length) {
        await pdfPage.evaluate((regions) => {
            regions.forEach((region) => {
                // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                // @ts-expect-error
                const mask = document.createElement('div');

                mask.style.cssText = `
                    position: fixed;
                    left: ${region.x}px;
                    top: ${region.y}px;
                    width: ${region.width}px;
                    height: ${region.height}px;
                    background: magenta;
                    z-index: 999999;
                `;

                // eslint-disable-next-line @typescript-eslint/ban-ts-comment
                // @ts-expect-error
                document.body.appendChild(mask);
            });
        }, maskRegions);
    }

    await expects(pdfPage).toHaveScreenshot(`${name}.png`, {
        maxDiffPixelRatio: 0.03,
        timeout: 5000,
    });

    await pdfPage.close();
}

/**
 * Deletes all documents of the given orders.
 * Documents restrict the deletion of their order, so they have to be removed before the order cleanup runs.
 * Dependent documents (e.g. credit notes, cancellation invoices) are deleted before the documents they reference.
 */
export async function deleteOrderDocuments(
    AdminApiContext: FixtureTypes['AdminApiContext'],
    orderIds: string[],
): Promise<void> {
    if (orderIds.length === 0) {
        return;
    }

    const response = await AdminApiContext.post('search/document', {
        data: {
            filter: [{ type: 'equalsAny', field: 'orderId', value: orderIds }],
            includes: { document: ['id', 'referencedDocumentId'] },
        },
    });
    if (!response.ok()) {
        throw new Error(`Searching documents failed with status ${response.status()}`);
    }

    const { data: documents } = (await response.json()) as {
        data: { id: string; referencedDocumentId: string | null }[];
    };
    const dependentDocuments = documents.filter((document) => document.referencedDocumentId);
    const referencedDocuments = documents.filter((document) => !document.referencedDocumentId);

    for (const batch of [dependentDocuments, referencedDocuments]) {
        if (batch.length === 0) {
            continue;
        }

        const deleteResponse = await AdminApiContext.post('_action/sync', {
            data: {
                'delete-documents': {
                    entity: 'document',
                    action: 'delete',
                    payload: batch.map(({ id }) => ({ id })),
                },
            },
        });
        if (!deleteResponse.ok()) {
            throw new Error(`Deleting documents failed with status ${deleteResponse.status()}`);
        }
    }
}
