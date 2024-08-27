const path = require('path');

class FilenameToChunkNamePlugin {
    constructor({isDevMode}) {
        this.isDevMode = isDevMode ?? false;
    }

    allChunkNames = {};
    apply(compiler) {
        compiler.hooks.compilation.tap('FilenameToChunkNamePlugin', (compilation) => {
            compilation.hooks.chunkIds.tap('FilenameToChunkNamePlugin', (chunks) => {
                chunks.forEach((chunk) => {
                    // do not change the name in development mode (it is using the original chunkIds: 'named')
                    if (!chunk.name && !this.isDevMode) {
                        const chunkModule = compilation.chunkGraph.getChunkRootModules(chunk)[0];
                        const rootModule = (chunkModule && chunkModule.rootModule) || chunkModule;
                        const rootPath = rootModule && rootModule.userRequest;
                        const name = rootPath && rootPath.split(path.sep).slice(-1)[0].replace('.js', '');
                        // only set the name if it is not already used by another chunk
                        if (name && !Object.values(this.allChunkNames).includes(name)) {
                            chunk.name = name;
                        }
                        this.allChunkNames[chunk.id] = name;
                    }
                });
            });
        });
    }
}

module.exports = FilenameToChunkNamePlugin;
