const { cloneDeep } = Shopware.Utils.object;
const types = Shopware.Utils.types;

export function initMissingSlots(block) {
    const repoFactory = Shopware.Service('repositoryFactory');
    const cmsService = Shopware.Service('cmsService');

    const cmsBlocks = cmsService.getCmsBlockRegistry();
    const slotRepository = repoFactory.create('cms_slot');

    const blockConfig = cmsBlocks[block.type];
    const existingSlots = new Set();

    block.slots.forEach((slot) => existingSlots.add(slot.slot));

    Object.keys(blockConfig.slots).forEach((slotName) => {
        if (existingSlots.has(slotName)) {
            return;
        }

        const slotConfig = blockConfig.slots[slotName];
        const slotElement = createSlotFromConfig(slotRepository, block.id, slotName, slotConfig);

        block.slots.add(slotElement);
    });
}

export function createSlotFromConfig(slotRepository, blockId, slotName, slotConfig) {
    const element = slotRepository.create(Shopware.Context.api);
    element.blockId = blockId;
    element.slot = slotName;

    if (typeof slotConfig === 'string') {
        element.type = slotConfig;
    } else if (types.isPlainObject(slotConfig)) {
        element.type = slotConfig.type;

        if (slotConfig.default && types.isPlainObject(slotConfig.default)) {
            Object.assign(element, cloneDeep(slotConfig.default));
        }
    }

    return element;
}
