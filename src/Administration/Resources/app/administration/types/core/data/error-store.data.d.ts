import { ShopwareError } from './ShopwareError';

export interface ErrorStore {
    addApiError(
        expression: string,
        error: ShopwareError,
        state: object,
        setReactive?: () => void
    ): void;

    createPathToError(
        expression: string,
        state: object,
        setReactive: () => void
    ): {
        store: object;
        field: string;
    };

    removeApiError(
        expression: string,
        state: object,
        deleteReactive?: () => void | null
    ): void;

    resetApiErrors(state: object): void;

    addSystemError(
        error: ShopwareError,
        id: string,
        state: object,
        setReactive?: () => void
    ): void;

    removeSystemError(
        id: string,
        state: object,
        deleteReactive?: () => void | null
    ): void;
}
