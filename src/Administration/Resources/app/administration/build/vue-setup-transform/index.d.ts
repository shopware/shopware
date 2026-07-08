/**
 * @sw-package framework
 */

export class ShopwareSetupTransformError extends Error {
    index: number;
}

/**
 * Names the filename-inferred transform path used by one Shopware setup SFC.
 */
export type ShopwareSetupTransformMode = 'base' | 'override';

/**
 * Result returned when an SFC contains a Shopware setup block and was lowered for Vue compilation.
 */
export type ShopwareSetupTransformResult = {
    code: string;
    map: null;
    mode: ShopwareSetupTransformMode;
    componentName: string;
    filename: string;
};

/**
 * Converts one Shopware setup SFC to Vue-compatible SFC source, or `null` for ordinary SFCs.
 */
export function transformShopwareSetupSfc(source: string, filename?: string): ShopwareSetupTransformResult | null;

/**
 * Runs the transform only for diagnostics and throws the same transform errors as compilation.
 */
export function validateShopwareSetupSfc(source: string, filename?: string): void;
