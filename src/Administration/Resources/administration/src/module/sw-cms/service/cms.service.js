export default {
    registerCmsElement,
    registerCmsBlock,
    getCmsElementConfigByName,
    getCmsBlockConfigByName,
    getCmsElementRegistry,
    getCmsBlockRegistry
};

const elementRegistry = {};
const blockRegistry = {};

function registerCmsElement(config) {
    if (!config.name || !config.component) {
        return false;
    }

    elementRegistry[config.name] = config;
    return true;
}

function getCmsElementConfigByName(name) {
    return elementRegistry[name];
}

function getCmsElementRegistry() {
    return elementRegistry;
}

function registerCmsBlock(config) {
    if (!config.name || !config.component) {
        return false;
    }

    blockRegistry[config.name] = config;
    return true;
}

function getCmsBlockConfigByName(name) {
    return blockRegistry[name];
}

function getCmsBlockRegistry() {
    return blockRegistry;
}
