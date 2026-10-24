const entries = new Map<string, number>();

export default {
    /**
     * @internal
     */
    register(name: string, priority?: number): boolean {
        entries.set(name, priority ?? 0);

        return true;
    },

    get(name: string): number | undefined {
        return entries.get(name);
    },
};
