/**
 * @sw-package framework
 */
import { createRequire } from 'node:module';
import { fileURLToPath, pathToFileURL } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';

type Position = { line: number; column: number };
type ProbeSpec = { marker: string; file: string };
type ProbeResult = {
    generatedIndex: number;
    authoredIndex: number;
    authoredPosition: Position | null;
    mappedPosition: { source: string | null; line: number | null } | null;
};
type RawSourceMap = { sources: string[]; sourcesContent?: (string | null)[]; mappings: string };
type MapConsumer = { originalPositionFor(position: Position): { source: string | null; line: number | null } };

const adminRoot = process.env.SHOPWARE_ADMIN_ROOT as string;
const here = path.dirname(fileURLToPath(import.meta.url));
const requireFromAdmin = createRequire(path.join(adminRoot, 'package.json'));

const { build } = (await import(pathToFileURL(requireFromAdmin.resolve('vite')).href)) as {
    build: (config: { configFile: string }) => Promise<unknown>;
};
const { SourceMapConsumer } = requireFromAdmin('source-map-js') as {
    SourceMapConsumer: new (map: RawSourceMap) => MapConsumer;
};

function positionForIndex(source: string, index: number): Position {
    const beforeIndex = source.slice(0, index);
    const lines = beforeIndex.split('\n');

    return {
        line: lines.length,
        column: lines[lines.length - 1].length,
    };
}

await build({ configFile: path.join(here, 'vite.config.ts') });

// Read the WRITTEN artifacts. The map file is serialized from the emitted asset, so an assertion
// against the in-memory chunk proves nothing about what actually ships.
const assetDirectory = path.join(here, 'dist/assets');
const chunkFileName = fs.readdirSync(assetDirectory).find((name) => name.endsWith('.js')) as string;
const code = fs.readFileSync(path.join(assetDirectory, chunkFileName), 'utf8');
const map = JSON.parse(fs.readFileSync(path.join(assetDirectory, `${chunkFileName}.map`), 'utf8')) as RawSourceMap;
const consumer = new SourceMapConsumer(map);

// A base component keeps its body in place while an override has its body moved into a callback, so
// the two exercise different halves of the mapping.
const probes: Record<string, ProbeSpec> = {
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

const results: Record<string, ProbeResult> = {};

Object.entries(probes).forEach(
    ([
        name,
        probe,
    ]) => {
        const generatedIndex = code.indexOf(probe.marker);
        const authoredSource = fs.readFileSync(path.join(here, probe.file), 'utf8');
        const authoredIndex = authoredSource.indexOf(probe.marker);

        results[name] = {
            generatedIndex,
            authoredIndex,
            authoredPosition: authoredIndex < 0 ? null : positionForIndex(authoredSource, authoredIndex),
            mappedPosition: generatedIndex < 0 ? null : consumer.originalPositionFor(positionForIndex(code, generatedIndex)),
        };
    },
);

process.stdout.write(
    JSON.stringify({
        sources: map.sources,
        loweredSourceCount: (map.sourcesContent ?? []).filter((content: string | null) =>
            (content ?? '').includes('__swSetupAuthor_'),
        ).length,
        ...results,
    }),
);
