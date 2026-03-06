import type { Locator } from '@playwright/test';
import type { expect } from '@fixtures/AcceptanceTest';

export type DocumentTypes = 'invoice' | 'credit_note' | 'delivery_note' | 'cancellation_invoice' | 'embedded_zugferd_e_invoice';

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

const invoiceMasks: MaskRegion[] = [
    { x: 112, y: 20, width: 35, height: 20 },
    { x: 395, y: 315, width: 40, height: 20 },
    { x: 860, y: 245, width: 80, height: 15 },
    { x: 868, y: 263, width: 55, height: 15 },
    { x: 842, y: 280, width: 55, height: 15 },
    { x: 340, y: 400, width: 315, height: 15 },
];

const documentMasks: Record<DocumentTypes, MaskRegion[]> = {
    invoice: invoiceMasks,
    embedded_zugferd_e_invoice: invoiceMasks,
    cancellation_invoice: [
        { x: 147, y: 18, width: 40, height: 20 },
        { x: 429, y: 315, width: 40, height: 20 },
        { x: 540, y: 315, width: 40, height: 20 },
        { x: 857, y: 228, width: 80, height: 15 },
        { x: 864, y: 245, width: 50, height: 15 },
        { x: 871, y: 263, width: 45, height: 15 },
        { x: 893, y: 280, width: 50, height: 15 },
        { x: 340, y: 400, width: 315, height: 15 },
    ],
    delivery_note: [
        { x: 150, y: 18, width: 40, height: 20 },
        { x: 435, y: 315, width: 40, height: 20 },
        { x: 535, y: 315, width: 130, height: 20 },
        { x: 340, y: 400, width: 320, height: 15 },
        { x: 862, y: 228, width: 82, height: 15 },
        { x: 867, y: 245, width: 55, height: 15 },
        { x: 841, y: 263, width: 55, height: 15 },
        { x: 879, y: 280, width: 55, height: 15 },
    ],
    credit_note: [
        { x: 137, y: 18, width: 40, height: 20 },
        { x: 418, y: 315, width: 40, height: 20 },
        { x: 554, y: 315, width: 40, height: 20 },
        { x: 344, y: 400, width: 235, height: 15 },
        { x: 861, y: 245, width: 85, height: 15 },
        { x: 867, y: 263, width: 55, height: 15 },
        { x: 840, y: 279, width: 55, height: 15 },
    ],
};

export async function screenshotPdfPopup(
    triggerLocator: Locator,
    expects: typeof expect,
    documentType: DocumentTypes,
) {
    const page = triggerLocator.page();
    const maskRegions = documentMasks[documentType];

    const [pdfPage] = await Promise.all([
        page.context().waitForEvent('page'),
        triggerLocator.click(),
    ]);

    await pdfPage.setViewportSize({ width: 1000, height: 1000 });
    await pdfPage.waitForLoadState('load');

    // wait for pdf viewer to render
    // eslint-disable-next-line playwright/no-wait-for-timeout
    await pdfPage.waitForTimeout(1000);

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

    await expects(pdfPage).toHaveScreenshot(`${documentType}-document.png`, {
        maxDiffPixelRatio: 0.03,
    });

    await pdfPage.close();
}
