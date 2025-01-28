/**
 * @sw-package framework
 */
const path = require('node:path');
const allChunkNames = [];
const debug = false;

class FilenameToChunkNamePlugin {
    apply(compiler) {
        compiler.hooks.compilation.tap('FilenameToChunkNamePlugin', (compilation) => {
            compilation.hooks.afterOptimizeChunkIds.tap('FilenameToChunkNamePlugin', (chunks) => {
                chunks.forEach((chunk) => {
                    if (!chunk.name) {
                        const chunkModule = compilation.chunkGraph.getChunkRootModules(chunk)[0];
                        const rootModule = chunkModule?.rootModule || chunkModule;
                        const rootPath = rootModule?.userRequest;
                        const targetName = rootPath && `${chunk.runtime}.${path.basename(rootPath, '.js')}`;
                        const isTargetNameSet = allChunkNames.includes(targetName);
                        const name = isTargetNameSet ? `${targetName}.${chunk.id}` : targetName;
                        chunk.name = name; // always set our custom chunk name for consistent naming
                        if (debug) {
                            if (isTargetNameSet) {
                                // eslint-disable-next-line no-console
                                console.log(`Chunk targetName '${targetName}' already exists, adding chunk id '${chunk.id}' to name`);
                            } else {
                                // eslint-disable-next-line no-console
                                console.log(`Setting chunk name to '${name}'`);
                            }
                        }
                        allChunkNames.push(name);
                    }
                });
                if (debug) {
                    // eslint-disable-next-line no-console
                    console.log('allChunkNames', allChunkNames);
                }
            });
        });
    }
}

module.exports = FilenameToChunkNamePlugin;
