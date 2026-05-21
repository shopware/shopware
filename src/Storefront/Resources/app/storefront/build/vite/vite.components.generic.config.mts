import path from 'node:path';
import { defineConfig, type UserConfig } from 'vite';
import { createComponentBuildConfig } from './component-config-factory';

export default defineConfig(async (): Promise<UserConfig> => {
    const componentRoot = process.env.COMPONENT_ROOT;
    const outDir = process.env.OUT_DIR;

    if (!componentRoot || !outDir) {
        throw new Error(
            '[vite.components.generic.config] COMPONENT_ROOT and OUT_DIR env vars must be set.',
        );
    }

    const namespace = process.env.COMPONENT_NAMESPACE ?? 'Storefront';

    return createComponentBuildConfig({
        componentRoot,
        outDir,
        namespace,
        storefrontAppDir: path.resolve(outDir, '../..'),
    });
});
