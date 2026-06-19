/**
 * @sw-package framework
 */

const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

(async () => {
    const adminRoot = process.env.SHOPWARE_ADMIN_ROOT;
    const root = __dirname;
    const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));
    const { build } = await import(pathToFileURL(requireFromAdmin.resolve('vite')).href);
    const { SourceMapConsumer } = requireFromAdmin('source-map-js');

    function positionForIndex(source, index) {
        const beforeIndex = source.slice(0, index);
        const lines = beforeIndex.split('\n');

        return {
            line: lines.length,
            column: lines[lines.length - 1].length,
        };
    }

    const result = await build({ configFile: path.join(root, 'vite.config.js') });
    const output = Array.isArray(result) ? result[0].output : result.output;
    const chunk = output.find((item) => item.type === 'chunk');
    const generatedIndex = chunk.code.indexOf('computed(() => "Hello from source")');
    const generatedPosition = positionForIndex(chunk.code, generatedIndex);
    const mappedPosition = new SourceMapConsumer(chunk.map).originalPositionFor(generatedPosition);

    process.stdout.write(JSON.stringify({
        generatedIndex,
        mappedPosition,
    }));
})();
