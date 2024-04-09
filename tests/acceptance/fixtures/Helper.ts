import { Image } from 'image-js';
import { AdminApiContext } from '@fixtures/AdminApiContext';
import { expect } from '@playwright/test';

export function createRandomImage(width = 800, height = 600) {

    const buffer = Buffer.alloc(width * height * 4);

    let i = 0;
    while (i < buffer.length) {
        buffer[i++] = Math.floor(Math.random() * 256);
    }
    return new Image(width, height, buffer);
}

export const getSalutationId = async (salutationKey: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/salutation', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'salutationKey',
                    value: salutationKey,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);
    return result.data[0].id;
};

export const getCurrencyFactor = async (isoCode: string, adminApiContext: AdminApiContext): Promise<number> => {
    const resp = await adminApiContext.post('./search/currency', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'isoCode',
                    value: isoCode,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { factor: number }[]; total: number };
    await expect(result.total).toBe(1);
    return result.data[0].factor;
};

export const getStateMachineId = async (technicalName: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/state-machine', {
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

export const getStateMachineStateId = async (stateMachineId: string, adminApiContext: AdminApiContext): Promise<string> => {
    const resp = await adminApiContext.post('./search/state-machine-state', {
        data: {
            limit: 1,
            filter: [
                {
                    type: 'equals',
                    field: 'stateMachineId',
                    value: stateMachineId,
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);
    return result.data[0].id;
};

export const getFlowId = async (eventName: string, adminApiContext: AdminApiContext): Promise<{ id: string }> => {
    const resp = await adminApiContext.post('./search-ids/flow', {
        data: {
            query: [
                {
                    query: {
                        type: 'contains',
                        field: 'flow.eventName',
                        value: eventName,
                    },
                },
            ],
        },
    });

    const result = (await resp.json()) as { data: { id: string }[]; total: number };
    await expect(result.total).toBe(1);
    return result.data[0];
};

export const getOrderTransactionId = async (orderId: string, adminApiContext: AdminApiContext): Promise<{ id: string }> =>{
    const orderTransactionResponse = await adminApiContext.get(`./order/${orderId}/transactions?_response`, {});
        await expect(orderTransactionResponse.ok()).toBeTruthy();
        const { data: orderTransaction } = await orderTransactionResponse.json();
        return orderTransaction[0].id;
};
