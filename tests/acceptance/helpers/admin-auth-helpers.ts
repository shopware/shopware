import { expect } from '@playwright/test';
import type { APIRequestContext, Page } from '@playwright/test';
import { generateSync } from 'otplib';

/**
 * Helpers for the ADMIN_AUTH acceptance tests (tests/AdminAuth).
 *
 * The tests enroll second factors (TOTP, recovery codes) for a dedicated, throwaway admin user so
 * the MFA challenge never affects the shared admin account or other tests. Enrollment-mutating
 * routes require a user-verified token (password grant with the `user-verified` scope), the same
 * guard the Administration uses for sensitive profile changes.
 */

export interface AdminAuthUser {
    id: string;
    username: string;
    password: string;
    firstName: string;
    lastName: string;
    email: string;
}

function apiUrl(path: string): string {
    return `${process.env['APP_URL']}api/${path}`;
}

/**
 * Fetches an access token for the given admin user via the password grant, including the
 * `user-verified` scope required by user writes and the MFA self-enrollment routes.
 */
export async function fetchUserVerifiedToken(
    request: APIRequestContext,
    username: string,
    password: string
): Promise<string> {
    const response = await request.post(apiUrl('oauth/token'), {
        data: {
            grant_type: 'password',
            client_id: 'administration',
            scope: 'user-verified',
            username,
            password,
        },
    });
    expect(response.ok(), 'Password grant with user-verified scope should succeed').toBeTruthy();

    const body = (await response.json()) as { access_token: string };

    return body.access_token;
}

/**
 * Creates a dedicated admin user for a login test. Uses the configured admin credentials
 * (SHOPWARE_ADMIN_USERNAME / SHOPWARE_ADMIN_PASSWORD) because user writes require a
 * user-verified token.
 */
export async function createAdminAuthUser(
    request: APIRequestContext,
    idPair: { id: string; uuid: string },
    localeId: string
): Promise<AdminAuthUser> {
    const adminToken = await fetchUserVerifiedToken(
        request,
        process.env['SHOPWARE_ADMIN_USERNAME'] || 'admin',
        process.env['SHOPWARE_ADMIN_PASSWORD'] || 'shopware'
    );

    const user: AdminAuthUser = {
        id: idPair.uuid,
        username: `admin_auth_${idPair.id}`,
        password: `shopware_${idPair.id}`,
        firstName: `${idPair.id} admin`,
        lastName: 'auth-test',
        email: `admin_auth_${idPair.id}@example.com`,
    };

    const response = await request.post(apiUrl('user'), {
        headers: { Authorization: `Bearer ${adminToken}` },
        data: {
            ...user,
            localeId,
            timezone: 'Europe/Berlin',
            admin: true,
        },
    });
    expect(response.ok(), 'Test admin user should be created').toBeTruthy();

    return user;
}

/**
 * Deletes a test admin user again. Enrolled MFA methods are removed with the user (cascade).
 */
export async function deleteAdminAuthUser(request: APIRequestContext, user: AdminAuthUser): Promise<void> {
    const adminToken = await fetchUserVerifiedToken(
        request,
        process.env['SHOPWARE_ADMIN_USERNAME'] || 'admin',
        process.env['SHOPWARE_ADMIN_PASSWORD'] || 'shopware'
    );

    await request.delete(apiUrl(`user/${user.id}`), {
        headers: { Authorization: `Bearer ${adminToken}` },
    });
}

/**
 * Enrolls a TOTP authenticator for the user of the given user-verified token via the
 * self-enrollment API and returns the shared secret for computing login codes.
 */
export async function enrollTotp(request: APIRequestContext, userVerifiedToken: string): Promise<string> {
    const optionsResponse = await request.post(apiUrl('_action/admin-auth/mfa/totp/register/options'), {
        headers: { Authorization: `Bearer ${userVerifiedToken}` },
        data: { label: 'Acceptance test authenticator' },
    });
    expect(optionsResponse.ok(), 'TOTP enrollment options should be issued').toBeTruthy();

    const options = (await optionsResponse.json()) as { id: string; secret: string };

    const verifyResponse = await request.post(apiUrl('_action/admin-auth/mfa/totp/register/verify'), {
        headers: { Authorization: `Bearer ${userVerifiedToken}` },
        data: { id: options.id, code: totpCode(options.secret) },
    });
    expect(verifyResponse.ok(), 'TOTP enrollment should be verified').toBeTruthy();

    return options.secret;
}

/**
 * (Re-)generates the recovery-code set for the user of the given user-verified token and returns
 * the plaintext codes.
 */
export async function generateRecoveryCodes(
    request: APIRequestContext,
    userVerifiedToken: string
): Promise<string[]> {
    const response = await request.post(apiUrl('_action/admin-auth/mfa/recovery-codes'), {
        headers: { Authorization: `Bearer ${userVerifiedToken}` },
        data: {},
    });
    expect(response.ok(), 'Recovery codes should be generated').toBeTruthy();

    const body = (await response.json()) as { codes: string[] };
    expect(body.codes.length).toBeGreaterThan(0);

    return body.codes;
}

/**
 * Computes the current 6-digit TOTP code for a secret issued by the enrollment API
 * (RFC 6238 defaults: SHA-1, 30 seconds period, 6 digits — matching the backend's OTPHP setup).
 */
export function totpCode(secret: string): string {
    return generateSync({ secret });
}

/**
 * A 6-digit code that is guaranteed to differ from the currently valid TOTP code.
 */
export function wrongTotpCode(secret: string): string {
    return totpCode(secret) === '000000' ? '111111' : '000000';
}

/**
 * Fills and submits the password login form of the Administration login screen.
 */
export async function submitPasswordLogin(adminPage: Page, username: string, password: string): Promise<void> {
    await adminPage.getByLabel(/Username|Email address/).fill(username);
    await adminPage.getByLabel('Password', { exact: true }).fill(password);
    await adminPage.getByRole('button', { name: 'Log in', exact: true }).click();
}

/**
 * The headline of the second-factor step rendered instead of the password form while a MFA
 * challenge is pending.
 */
export function mfaHeadline(adminPage: Page) {
    return adminPage.getByRole('heading', { name: 'Two-factor authentication' });
}

/**
 * Asserts that the login completed and the Administration is usable for the given user.
 */
export async function expectLoggedIn(adminPage: Page, user: AdminAuthUser): Promise<void> {
    await adminPage.waitForURL((url) => !url.hash.startsWith('#/login'), { timeout: 60_000 });
    await expect(adminPage.getByText(`${user.firstName} ${user.lastName}`).first()).toBeVisible({
        timeout: 60_000,
    });
}
