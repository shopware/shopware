import fs from 'node:fs';
import path from 'node:path';
import { createHash, randomBytes } from 'node:crypto';
import { Models, Enums, Serialize, Spec } from '@cyclonedx/cyclonedx-library';
import sbom from 'rollup-plugin-sbom';

const PLUGIN_NAME = 'shopware-storefront-sbom';

const REPORT_PATH = ['Resources', 'sbom', 'storefront'];

/**
 * Extension source trees are untrusted data: their build runs without their Vite config or install scripts.
 * Refuse a report directory reached through symlinks below the bundle root, so a shipped link cannot redirect
 * report writes or deletions outside of it. Returns null when the directory is missing and `create` is false.
 */
function reportDirectory(bundleRoot, { create = false } = {}) {
    let directory = fs.realpathSync(bundleRoot);
    for (const segment of REPORT_PATH) {
        directory = path.join(directory, segment);
        let stat = fs.lstatSync(directory, { throwIfNoEntry: false });
        if (!stat) {
            if (!create) {
                return null;
            }
            fs.mkdirSync(directory);
            stat = fs.lstatSync(directory);
        }
        if (stat.isSymbolicLink() || !stat.isDirectory()) {
            throw new Error(`[${PLUGIN_NAME}] Refusing to use SBOM report path that is not a real directory: ${directory}`);
        }
    }
    return directory;
}

function outputDirectory(config, outputOptions) {
    const cwd = path.resolve(config.build.rolldownOptions?.cwd ?? process.cwd());
    if (outputOptions.file) {
        return path.dirname(path.resolve(cwd, outputOptions.file));
    }
    if (outputOptions.dir) {
        return path.resolve(cwd, outputOptions.dir);
    }
    return path.resolve(config.root, config.build.outDir);
}

function outputKey(config, outputOptions, bundle) {
    return JSON.stringify([
        outputDirectory(config, outputOptions),
        outputOptions.format,
        Object.values(bundle).filter(item => item.type === 'chunk').map(chunk => chunk.fileName).sort(),
    ]);
}

function composerMetadata(bundleRoot) {
    const root = path.resolve(bundleRoot);
    let directory = root;
    while (true) {
        const composerPath = path.join(directory, 'composer.json');
        if (fs.existsSync(composerPath)) {
            const composer = JSON.parse(fs.readFileSync(composerPath, 'utf8'));
            // Plugin resources commonly live below src/, while the Composer
            // project is above it. Do not inherit an enclosing shop's version.
            return directory === root || composer.type === 'shopware-platform-plugin' ? composer : {};
        }
        const parent = path.dirname(directory);
        if (parent === directory) {
            return {};
        }
        directory = parent;
    }
}

export function clearStorefrontSbomReports({ bundleRoot, target }) {
    if (!fs.existsSync(bundleRoot)) {
        return;
    }
    const directory = reportDirectory(bundleRoot);
    if (!directory) {
        return;
    }

    for (const file of fs.readdirSync(directory)) {
        if (file === `${target}.cdx.json` || (file.startsWith(`${target}-`) && file.endsWith('.cdx.json'))) {
            fs.rmSync(path.join(directory, file));
        }
    }
}

/** Find package ownership from resolved files, including nested package versions. */
function packageIdentity(id, cache) {
    if (cache.has(id)) {
        return cache.get(id);
    }

    let identity = null;
    if (!id.startsWith('\0') && /[/\\]node_modules[/\\]/.test(id)) {
        let directory = path.dirname(id.split('?')[0]);
        while (directory !== path.dirname(directory) && path.basename(directory) !== 'node_modules') {
            const manifest = path.join(directory, 'package.json');
            if (fs.existsSync(manifest)) {
                const pkg = JSON.parse(fs.readFileSync(manifest, 'utf8'));
                if (pkg.name && pkg.version) {
                    identity = `${pkg.name}@${pkg.version}`;
                    break;
                }
            }
            directory = path.dirname(directory);
        }
        if (!identity) {
            throw new Error(`[${PLUGIN_NAME}] Missing resolved package name/version for ${id}`);
        }
    }

    cache.set(id, identity);
    return identity;
}

function componentIdentity(component) {
    const name = component.group ? `${component.group}/${component.name}` : component.name;
    return `${name}@${component.version}`;
}

/**
 * Adapt the generator's parsed-module traversal to the modules retained in the output.
 * The generator still supplies package metadata, licenses, PURLs and serialization.
 */
function collectGraph(context, chunks) {
    const owners = new Map();
    const cache = new Map();
    const virtualModules = new Set();
    const externalModules = new Set();
    const edges = new Map();
    const chunkNames = new Set(chunks.map(chunk => chunk.fileName));

    for (const chunk of chunks) {
        for (const imported of [...chunk.imports, ...chunk.dynamicImports]) {
            if (!chunkNames.has(imported)) {
                externalModules.add(imported);
            }
        }
        for (const id of chunk.moduleIds) {
            if (id.startsWith('\0') || id.startsWith('virtual:')) {
                virtualModules.add(id.replace(/^\0/, 'virtual:'));
            }
            if (chunk.modules[id]?.renderedLength === 0) {
                continue;
            }
            owners.set(id, packageIdentity(id, cache));
        }
    }

    const graphModules = new Map(owners);
    for (const chunk of chunks) {
        // An entry containing only re-exports can have no rendered code of its own.
        if (chunk.isEntry && chunk.facadeModuleId && !graphModules.has(chunk.facadeModuleId)) {
            graphModules.set(chunk.facadeModuleId, null);
        }
    }
    for (const [id, owner] of graphModules) {
        const dependencies = edges.get(owner) ?? new Set();
        edges.set(owner, dependencies);
        const visited = new Set([id]);

        function visit(importedId) {
            if (visited.has(importedId)) {
                return;
            }
            visited.add(importedId);
            const info = context.getModuleInfo(importedId);
            if (info?.isExternal) {
                externalModules.add(importedId);
                return;
            }
            if (owners.has(importedId)) {
                const dependency = owners.get(importedId);
                if (dependency !== owner && dependency !== null) {
                    dependencies.add(dependency);
                }
                return;
            }
            // Re-export barrels may contain no rendered code themselves.
            for (const child of [...info?.importedIds ?? [], ...info?.dynamicallyImportedIds ?? []]) {
                visit(child);
            }
        }

        const info = context.getModuleInfo(id);
        for (const importedId of [...info?.importedIds ?? [], ...info?.dynamicallyImportedIds ?? []]) {
            visit(importedId);
        }
        if (info?.isEntry && owner !== null) {
            const rootEdges = edges.get(null) ?? new Set();
            rootEdges.add(owner);
            edges.set(null, rootEdges);
        }
    }

    return { owners, edges, virtualModules, externalModules };
}

function inlineWorkerId(id) {
    return /[?&](?:sharedworker|worker)(?:&|$)/.test(id) && /[?&]inline(?:&|$)/.test(id)
        ? id.split('?')[0]
        : null;
}

/** Merge worker graphs while their emitted assets or retained inline wrappers are present. */
function collectBuildGraph(context, chunks, bundle, workers) {
    const graph = collectGraph(context, chunks);
    const modules = new Map();
    const pending = new Map([...workers.values()].map(worker => [worker, new Set(worker.chunks)]));
    let changed = true;
    while (changed) {
        changed = false;
        const inlineWorkers = new Set([...graph.owners.keys()].map(inlineWorkerId).filter(Boolean));
        for (const [worker, remaining] of pending) {
            const retained = [...remaining].filter(chunk => bundle[chunk.fileName] || (chunk === worker.entry && inlineWorkers.has(worker.id)));
            if (retained.length === 0) {
                continue;
            }
            for (const chunk of retained) {
                remaining.delete(chunk);
            }
            changed = true;
            chunks.push(...retained);
            const workerGraph = collectGraph({ getModuleInfo: id => worker.modules.get(id) }, retained);
            for (const [id, owner] of workerGraph.owners) {
                graph.owners.set(id, owner);
                modules.set(id, worker.modules.get(id));
            }
            for (const [owner, dependencies] of workerGraph.edges) {
                const merged = graph.edges.get(owner) ?? new Set();
                for (const dependency of dependencies) {
                    merged.add(dependency);
                }
                graph.edges.set(owner, merged);
            }
            for (const id of workerGraph.virtualModules) {
                graph.virtualModules.add(id);
            }
            for (const id of workerGraph.externalModules) {
                graph.externalModules.add(id);
            }
        }
    }
    return { graph, modules };
}

/** Inline workers contribute packages to their containing file, without inventing an emitted file. */
function fileModuleIds(chunk, graph, workers) {
    const ids = new Set(chunk.moduleIds);
    for (const id of ids) {
        const worker = graph.owners.has(id) && workers.get(inlineWorkerId(id));
        if (worker) {
            for (const workerId of worker.entry.moduleIds) {
                ids.add(workerId);
            }
        }
    }
    return ids;
}

function decorateBom(bom, graph, options, outputIdentity) {
    const root = new Models.Component(Enums.ComponentType.Library, `${options.bundleName}/${options.target}`, {
        bomRef: `shopware:${options.bundleName}:${options.target}:${outputIdentity}`,
    });
    const composer = composerMetadata(options.bundleRoot);
    root.version = composer.version;
    root.description = composer.description;
    bom.metadata.component = root;

    const shipped = new Set([...graph.owners.values()].filter(Boolean));
    const components = new Map();
    for (const component of bom.components) {
        const identity = componentIdentity(component);
        if (!shipped.has(identity)) {
            bom.components.delete(component);
            continue;
        }
        component.dependencies.clear();
        components.set(identity, component);
    }
    for (const identity of shipped) {
        if (!components.has(identity)) {
            throw new Error(`[${PLUGIN_NAME}] Missing bundled package metadata: ${identity}`);
        }
    }

    for (const [owner, dependencies] of graph.edges) {
        const component = owner === null ? root : components.get(owner);
        for (const dependency of dependencies) {
            component.dependencies.add(components.get(dependency).bomRef);
        }
    }
    for (const id of [...graph.externalModules].sort()) {
        bom.metadata.properties.add(new Models.Property('shopware:sbom:external-module', id));
    }
    for (const id of [...graph.virtualModules].sort()) {
        bom.metadata.properties.add(new Models.Property('shopware:sbom:virtual-module', id));
    }

    return { root, components };
}

/** One isolated instance per production build; safe for concurrently built bundles. */
export function storefrontSbomPlugin(options) {
    let enabled = false;
    let config;
    let failed = false;
    let reports = new Map();
    let outputCount = 0;
    let started = false;
    // Vite caches worker builds across watch rebuilds and output formats.
    // Keep their graphs and select only workers present in each actual output.
    const workers = new Map();

    const plugin = {
        name: PLUGIN_NAME,
        api: { orchestrated: options.orchestrated === true },
        enforce: 'post',
        apply: 'build',
        config(userConfig) {
            const createWorkerPlugins = userConfig.worker?.plugins;
            return {
                worker: {
                    plugins: () => [
                        typeof createWorkerPlugins === 'function' ? createWorkerPlugins() : createWorkerPlugins ?? [],
                        {
                            name: `${PLUGIN_NAME}-worker`,
                            enforce: 'post',
                            generateBundle: {
                                order: 'post',
                                handler(_outputOptions, bundle) {
                                    if (!enabled) {
                                        return;
                                    }
                                    const chunks = Object.values(bundle).filter(item => item.type === 'chunk');
                                    const entry = chunks.find(chunk => chunk.isEntry && chunk.facadeModuleId);
                                    if (!entry) {
                                        return;
                                    }
                                    const id = entry.facadeModuleId.split('?')[0];
                                    workers.set(id, {
                                        id,
                                        entry,
                                        chunks,
                                        modules: new Map([...this.getModuleIds()].map(moduleId => {
                                            const info = this.getModuleInfo(moduleId);
                                            return [moduleId, {
                                                isEntry: info.isEntry,
                                                isExternal: info.isExternal,
                                                importedIds: [...info.importedIds],
                                                dynamicallyImportedIds: [...info.dynamicallyImportedIds],
                                            }];
                                        })),
                                    });
                                },
                            },
                        },
                    ],
                },
            };
        },
        configResolved(resolvedConfig) {
            config = resolvedConfig;
            // The orchestrator knows the owning bundle even when a custom config
            // changes its component namespace or source directory.
            const candidates = config.plugins.filter(candidate => candidate.name === PLUGIN_NAME);
            enabled = config.isProduction && config.mode !== 'development'
                && (candidates.find(candidate => candidate.api.orchestrated) ?? candidates[0]) === plugin;
        },
        watchChange() {
            started = false;
        },
        buildStart() {
            if (!enabled) {
                return;
            }
            // Vite 8 starts a separate Rolldown build for each output format.
            if (!started) {
                failed = false;
                reports = new Map();
                outputCount = 0;
                clearStorefrontSbomReports(options);
                started = true;
            }
        },
        buildEnd(error) {
            if (error) {
                failed = true;
            }
        },
        renderError() {
            failed = true;
        },
        generateBundle: {
            order: 'post',
            async handler(outputOptions, bundle) {
                if (!enabled) {
                    return;
                }
                const chunks = Object.values(bundle).filter(item => item.type === 'chunk');
                if (chunks.length === 0) {
                    return;
                }
                const { graph, modules } = collectBuildGraph(this, chunks, bundle, workers);
                const outputIndex = outputCount++;
                const outputIdentity = `${outputIndex + 1}-${outputOptions.format}`;
                let currentBom;
                let currentComponents;
                const generator = sbom({
                    specVersion: '1.6',
                    rootComponentType: 'library',
                    autodetect: false,
                    includeWellKnown: false,
                    outFormats: ['json'],
                    saveTimestamp: false,
                    afterCollect(bom) {
                        currentBom = bom;
                        currentComponents = decorateBom(bom, graph, options, outputIdentity);
                    },
                    properties: [{
                        name: 'shopware:sbom:coverage',
                        value: 'Vite JavaScript chunks and bundled workers, including inline workers; excludes webpack outputs, copied assets and CSS. '
                            + 'Virtual/generated modules have no inferred npm package provenance.',
                    }],
                });
                let serialized;
                const context = new Proxy(this, {
                    get(target, key) {
                        if (key === 'getModuleInfo') {
                            return (id) => {
                                const info = target.getModuleInfo(id) ?? modules.get(id);
                                return info && {
                                    id,
                                    importedIds: info.importedIds.filter(importedId => graph.owners.has(importedId)),
                                    dynamicallyImportedIds: info.dynamicallyImportedIds.filter(importedId => graph.owners.has(importedId)),
                                };
                            };
                        }
                        if (key === 'emitFile') {
                            return (asset) => {
                                serialized = asset.source;
                                return 'shopware-sbom';
                            };
                        }
                        if (key === 'warn') {
                            return (message) => {
                                // Vite 8 uses Rolldown; the generator also probes optional Rollup.
                                if (typeof message === 'string' && message.startsWith('Error during auto-registration of tool "rollup": Error: Cannot find module \'rollup\'')) {
                                    target.debug(message);
                                    return;
                                }
                                target.warn(message);
                            };
                        }
                        const value = Reflect.get(target, key, target);
                        return typeof value === 'function' ? value.bind(target) : value;
                    },
                });
                const filteredBundle = Object.fromEntries(chunks.map((chunk, index) => [`${index}:${chunk.fileName}`, {
                    ...chunk,
                    moduleIds: chunk.moduleIds.filter(id => graph.owners.has(id)),
                    imports: [],
                    dynamicImports: [],
                }]));

                // Reset the generator for every output, including custom multi-output configs.
                await generator.buildStart.call(context, {});
                await generator.generateBundle.call(context, outputOptions, filteredBundle, true);
                const document = JSON.parse(serialized);
                const directory = outputDirectory(config, outputOptions);
                const { root, components } = currentComponents;
                const emittedChunks = new Map(chunks.filter(chunk => bundle[chunk.fileName]).map(chunk => [chunk.fileName, chunk]));
                for (const chunk of emittedChunks.values()) {
                    const file = new Models.Component(Enums.ComponentType.File, chunk.fileName, {
                        bomRef: `${root.bomRef.value}:file:${chunk.fileName}`,
                    });
                    for (const id of fileModuleIds(chunk, graph, workers)) {
                        const owner = graph.owners.get(id);
                        if (owner) {
                            file.dependencies.add(components.get(owner).bomRef);
                        }
                    }
                    currentBom.components.add(file);
                    root.dependencies.add(file.bomRef);
                }
                currentBom.metadata.properties.add(new Models.Property(
                    'shopware:sbom:output-directory',
                    path.relative(options.bundleRoot, directory).split(path.sep).join('/'),
                ));
                reports.set(outputKey(config, outputOptions, bundle), {
                    bom: currentBom,
                    directory,
                    filename: outputIndex === 0
                        ? `${options.target}.cdx.json`
                        : `${options.target}-${outputIdentity}.cdx.json`,
                });
                // Ensure the generator produced the requested format before persistence.
                if (document.bomFormat !== 'CycloneDX' || document.specVersion !== '1.6') {
                    this.error('Invalid Storefront SBOM format');
                }
            },
        },
        writeBundle: {
            order: 'post',
            sequential: true,
            async handler(outputOptions, bundle) {
                if (!enabled || failed) {
                    return;
                }
                const report = reports.get(outputKey(config, outputOptions, bundle));
                if (!report) {
                    return;
                }
                const serializer = new Serialize.JsonSerializer(new Serialize.JSON.Normalize.Factory(Spec.Spec1dot6));
                for (const component of report.bom.components) {
                    if (component.type === Enums.ComponentType.File) {
                        const contents = await fs.promises.readFile(path.join(report.directory, component.name));
                        component.hashes.set(Enums.HashAlgorithm['SHA-256'], createHash('sha256').update(contents).digest('hex'));
                    }
                }
                const directory = reportDirectory(options.bundleRoot, { create: true });
                const destination = path.join(directory, report.filename);
                // Exclusive creation never follows or reuses a planted file; rename replaces a planted link itself.
                const temporary = path.join(directory, `.${report.filename}.${randomBytes(8).toString('hex')}.tmp`);
                const handle = await fs.promises.open(
                    temporary,
                    fs.constants.O_WRONLY | fs.constants.O_CREAT | fs.constants.O_EXCL | (fs.constants.O_NOFOLLOW ?? 0),
                    0o644,
                );
                try {
                    try {
                        await handle.writeFile(`${serializer.serialize(report.bom, { sortLists: true, space: 2 })}\n`);
                    } finally {
                        await handle.close();
                    }
                    await fs.promises.rename(temporary, destination);
                } catch (error) {
                    await fs.promises.rm(temporary, { force: true });
                    throw error;
                }
            },
        },
    };

    return plugin;
}
