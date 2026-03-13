import type { Locator } from '@playwright/test';
import type { expect } from '@fixtures/AcceptanceTest';

export type DocumentTypes =
    'invoice' |
    'credit_note' |
    'delivery_note' |
    'cancellation_invoice' |
    'zugferd_embedded_invoice' |
    'zugferd_embedded_cancellation_invoice' |
    'zugferd_embedded_credit_note';

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
