type NativeExtensionTargets = {
    component: string;
    blocks?: string[];
};

const nativeBlockExtensionTargets = new Set<string>();

export function registerNativeExtensionTargets(targets: NativeExtensionTargets): void {
    targets.blocks?.forEach((blockName) => nativeBlockExtensionTargets.add(blockName));
    console.log(nativeBlockExtensionTargets);
}

export function getNativeBlockExtensionTargets(): ReadonlySet<string> {
    return nativeBlockExtensionTargets;
}