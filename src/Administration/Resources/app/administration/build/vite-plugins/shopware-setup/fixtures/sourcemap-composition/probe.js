/**
 * @sw-package framework
 */

const { createRequire } = require('node:module');
const { pathToFileURL } = require('node:url');
const fs = require('node:fs');
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

    await build({ configFile: path.join(root, 'vite.config.js') });

    // Read the WRITTEN artifacts. The map file is serialized from the emitted asset, so an assertion
    // against the in-memory chunk proves nothing about what actually ships.
    const assetDirectory = path.join(root, 'dist/assets');
    const chunkFileName = fs.readdirSync(assetDirectory).find((name) => name.endsWith('.js'));
    const code = fs.readFileSync(path.join(assetDirectory, chunkFileName), 'utf8');
    const map = JSON.parse(fs.readFileSync(path.join(assetDirectory, `${chunkFileName}.map`), 'utf8'));
    const consumer = new SourceMapConsumer(map);

    // A base component keeps its body in place while an override has its body moved into a callback, so
    // the two exercise different halves of the mapping.
    const probes = {
        base: {
            // Quote style is not preserved through the build, so the marker avoids quotes entirely.
            marker: 'Hello from source',
            file: 'src/sw-nested-component.vue',
        },
        override: {
            marker: 'and the override',
            file: 'src/sw-nested-component.override.vue',
        },
    };

    const results = {};

    Object.entries(probes).forEach(
        ([
            name,
            probe,
        ]) => {
            const generatedIndex = code.indexOf(probe.marker);
            const authoredSource = fs.readFileSync(path.join(root, probe.file), 'utf8');
            const authoredIndex = authoredSource.indexOf(probe.marker);

            results[name] = {
                generatedIndex,
                authoredIndex,
                authoredPosition: authoredIndex < 0 ? null : positionForIndex(authoredSource, authoredIndex),
                mappedPosition:
                    generatedIndex < 0 ? null : consumer.originalPositionFor(positionForIndex(code, generatedIndex)),
            };
        },
    );

    process.stdout.write(
        JSON.stringify({
            sources: map.sources,
            loweredSourceCount: (map.sourcesContent ?? []).filter((content) => (content ?? '').includes('__swSetupAuthor_'))
                .length,
            ...results,
        }),
    );
})();
