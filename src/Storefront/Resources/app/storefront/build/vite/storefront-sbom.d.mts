import type { Plugin } from 'vite';

export type StorefrontSbomOptions = {
    bundleName: string;
    bundleRoot: string;
    target: 'shopware' | 'components';
    orchestrated?: boolean;
};

export function storefrontSbomPlugin(options: StorefrontSbomOptions): Plugin;
export function clearStorefrontSbomReports(options: Pick<StorefrontSbomOptions, 'bundleRoot' | 'target'>): void;
