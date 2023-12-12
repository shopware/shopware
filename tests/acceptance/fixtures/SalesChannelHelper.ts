import { AdminApiContext } from './AdminApiContext';
import { expect } from '@playwright/test';

export const getLanguageData = async (
    languageCode: string,
    adminApiContext: AdminApiContext
): Promise<{ id: string; localeId: string }> => {
    const resp = await adminApiContext.post('./search/language', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'translationCode.code',
                    value: languageCode,
                },
            ],
            associations: { translationCode: {} },
        },
    });

    const result = (await resp.json()) as { data: { id: string; translationCode: { id: string } }[]; total: number };

    await expect(result.total).toBe(1);

    return {
        id: result.data[0].id,
        localeId: result.data[0].translationCode.id,
    };
};

export const getSnippetSetId = async (languageCode: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/snippet-set', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'iso',
                    value: languageCode,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);

    return result.data[0].id;
};

export const getCurrencyId = async (adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/currency', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'isoCode',
                    value: 'EUR',
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);

    return result.data[0].id;
};

export const getTaxId = async (adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/tax', {
        data: {
            limit: 1,
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);

    return result.data[0].id;
};

export const getPaymentMethodId = async (handlerId: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/payment-method', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'handlerIdentifier',
                    value: handlerId,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string; active: boolean }[]; total: number };
    await expect(result.total).toBe(1);
    await expect(result.data[0].active).toBe(true);

    return result.data[0].id;
};

export const getDefaultShippingMethod = async (adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/shipping-method', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'name',
                    value: 'Standard',
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string; active: boolean }[]; total: number };
    await expect(result.total).toBe(1);
    await expect(result.data[0].active).toBe(true);

    return result.data[0].id;
};

export const getCountryId = async (iso2: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/country', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'iso',
                    value: iso2,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);

    return result.data[0].id;
};

export const getThemeId = async (technicalName: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/theme', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'technicalName',
                    value: technicalName,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);

    return result.data[0].id;
};
