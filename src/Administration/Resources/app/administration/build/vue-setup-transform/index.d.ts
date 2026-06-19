/**
 * @sw-package framework
 */

export class ShopwareSetupTransformError extends Error {
    index: number;
}

export type ShopwareSetupTransformMode = 'base' | 'override';

export type ShopwareSetupTransformResult = {
    code: string;
    map: {
        version: number;
        file?: string;
        sources: string[];
        sourcesContent?: string[];
        names: string[];
        mappings: string;
        toString(): string;
        toUrl(): string;
    };
    mode: ShopwareSetupTransformMode;
    componentName: string;
    filename: string;
};

export function transformShopwareSetupSfc(source: string, filename?: string): ShopwareSetupTransformResult | null;

export function validateShopwareSetupSfc(source: string, filename?: string): void;
