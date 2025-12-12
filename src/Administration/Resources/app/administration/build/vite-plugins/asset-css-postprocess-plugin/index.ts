import { Plugin } from 'vite';

/**
 * Vite plugin that rewrites absolute asset URLs in generated CSS to be relative.
 */
export default function stripAssetsFolderInCss(folderToStrip: string): Plugin {
    return {
        name: 'asset-css-postprocess-plugin',
        generateBundle(_, bundle) {
            for (const [
                fileName,
                file,
            ] of Object.entries(bundle)) {
                if (fileName.endsWith('.css') && file.type === 'asset' && typeof file.source === 'string') {
                    // Replace absolute prefixed URLs with relative ones
                    // Example: url(/bundles/.../assets/Inter-XXX.woff2?v=3.19) -> url(./Inter-XXX.woff2?v=3.19)
                    file.source = file.source.replace(new RegExp(`${folderToStrip}([^)"'\\s]+)`, 'g'), './$1');
                }
            }
        },
    };
}
