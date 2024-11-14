const path = require('node:path');
const allChunkNames = [];
const debug = false;

class FilenameToChunkNamePlugin {
    apply(compiler) {
        compiler.hooks.compilation.tap('FilenameToChunkNamePlugin', (compilation) => {
            compilation.hooks.chunkIds.tap('FilenameToChunkNamePlugin', (chunks) => {
                chunks.forEach((chunk) => {
                    // do not change the name in development mode (it is using the original chunkIds: 'named')
                    if (!chunk.name) {
                        const chunkModule = compilation.chunkGraph.getChunkRootModules(chunk)[0];
                        const rootModule = (chunkModule && chunkModule.rootModule) || chunkModule;
                        const rootPath = rootModule && rootModule.userRequest;
                        const name = rootPath && rootPath.split(path.sep).slice(-1)[0].replace('.js', '');
                        // only set the name if it is not already used by another chunk
                        if (name && !allChunkNames.includes(name)) {
                            chunk.name = name;

                            if (debug) {
                                // eslint-disable-next-line no-console
                                console.log(`Setting chunk name to '${name}'`);
                            }
                        } else {
                            if (debug) {
                                // eslint-disable-next-line no-console
                                console.log(`Chunk name '${name}' already exists, keeping original name`);
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
