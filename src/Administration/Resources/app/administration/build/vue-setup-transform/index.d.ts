/**
 * @sw-package framework
 */

export class ShopwareSetupTransformError extends Error {
    index: number;
}

export type ShopwareSetupTransformMode = 'base' | 'override';

export type ShopwareSetupTransformResult = {
    code: string;
    map: null;
    mode: ShopwareSetupTransformMode;
    filename: string;
};

export function transformShopwareSetupSfc(source: string, filename?: string): ShopwareSetupTransformResult | null;

export function validateShopwareSetupSfc(source: string, filename?: string): void;
