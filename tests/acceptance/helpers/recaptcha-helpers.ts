import type { Page } from '@playwright/test';
import { expect } from '@playwright/test';

/**
 * Verifies that reCAPTCHA script is not loaded before cookie consent
 * @param page - The Playwright page object
 * @param test - The test object for step creation
 * @param version - The reCAPTCHA version (V2 or V3) for better test step naming
 */
export async function verifyRecaptchaScriptNotLoaded(
    page: Page,
    test: { step: (name: string, fn: () => Promise<void>) => Promise<void> },
    version: 'V2' | 'V3' = 'V3'
): Promise<void> {
    await test.step(`Verify reCaptcha ${version} script is not loaded before cookie consent`, async () => {
        const reCaptchaScript = page.locator('#recaptcha-script');
        await expect(reCaptchaScript).toHaveAttribute('data-src');
        await expect(reCaptchaScript).not.toHaveAttribute('src');
    });
}

/**
 * Waits for reCAPTCHA script to be properly loaded after cookie consent
 * @param page - The Playwright page object
 * @param retries - Number of retry attempts (default: 3)
 */
export async function waitForRecaptchaScriptLoaded(page: Page, retries = 3): Promise<void> {
    await page.waitForLoadState('networkidle');
    // Retry mechanism for script loading
    let remainingRetries = retries;
    while (remainingRetries > 0) {
        try {
            await page.waitForSelector('script[src*="recaptcha"]', {
                state: 'attached',
                timeout: 5000
            });
            break;
        } catch (error) {
            remainingRetries--;
            if (remainingRetries === 0) throw error;
            await page.waitForTimeout(1000);
        }
    }
}

/**
 * Verifies that reCAPTCHA protection notice is visible
 * @param page - The Playwright page object
 * @param test - The test object for step creation
 * @param version - The reCAPTCHA version (V2 or V3) for better test step naming
 */
export async function verifyRecaptchaProtectionNotice(
    page: Page,
    test: { step: (name: string, fn: () => Promise<void>) => Promise<void> },
    version: 'V2' | 'V3' = 'V3'
): Promise<void> {
    await test.step(`Verify reCaptcha ${version} protection notice is visible`, async () => {
        const reCaptchaNotice = page.getByText('This site is protected by reCAPTCHA');
        await expect(reCaptchaNotice).toBeVisible();
    });
}

/**
 * Complete reCAPTCHA setup flow: verify script not loaded, accept cookies, wait for script, verify notice
 * @param page - The Playwright page object
 * @param test - The test object for step creation
 * @param acceptTechnicalRequiredCookies - The cookie acceptance function
 * @param version - The reCAPTCHA version (V2 or V3) for better test step naming
 */
export async function setupRecaptchaFlow(
    page: Page,
    test: { step: (name: string, fn: () => Promise<void>) => Promise<void> },
    acceptTechnicalRequiredCookies: () => Promise<void>,
    version: 'V2' | 'V3' = 'V3'
): Promise<void> {
    await verifyRecaptchaScriptNotLoaded(page, test, version);
    await acceptTechnicalRequiredCookies();
    await waitForRecaptchaScriptLoaded(page);
    await verifyRecaptchaProtectionNotice(page, test, version);
}

/**
 * Enhanced cookie acceptance with reCAPTCHA verification
 * This combines the standard cookie acceptance with reCAPTCHA-specific waiting and verification
 * @param page - The Playwright page object
 * @param test - The test object for step creation
 * @param acceptTechnicalRequiredCookies - The cookie acceptance function
 * @param version - The reCAPTCHA version (V2 or V3) for better test step naming
 */
export async function acceptTechnicalRequiredCookiesWithRecaptcha(
    page: Page,
    test: { step: (name: string, fn: () => Promise<void>) => Promise<void> },
    acceptTechnicalRequiredCookies: () => Promise<void>,
    version: 'V2' | 'V3' = 'V3'
): Promise<void> {
    await test.step(`Accept technical required cookies and verify reCaptcha ${version} setup`, async () => {
        await acceptTechnicalRequiredCookies();
        await waitForRecaptchaScriptLoaded(page);
    });
}
