import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { relative } from 'node:path';

const FILE = process.env.KNIP_BASELINE ?? 'knip-baseline.json';

const write = (keys) => writeFileSync(FILE, `${JSON.stringify([...keys].sort(), null, 1)}\n`);

export default (data) => {
    const current = [];
    for (const [type, byFile] of Object.entries(data.issues)) {
        for (const [file, bySymbol] of Object.entries(byFile ?? {})) {
            for (const symbol of Object.keys(bySymbol)) {
                current.push({ type, file, symbol, key: `${type}|${relative(data.cwd, file)}|${symbol}` });
            }
        }
    }

    if (process.env.KNIP_BASELINE_WRITE) {
        write(current.map((i) => i.key));
    }

    let baseline = new Set(existsSync(FILE) ? JSON.parse(readFileSync(FILE, 'utf8')) : []);

    // Keeps only entries knip still reports, so pruning never adds new findings to the baseline
    if (process.env.KNIP_BASELINE_PRUNE) {
        const currentKeys = new Set(current.map((i) => i.key));
        baseline = new Set([...baseline].filter((key) => currentKeys.has(key)));
        write(baseline);
    }

    for (const { type, file, symbol, key } of current) {
        if (baseline.delete(key)) {
            delete data.issues[type][file][symbol];
            data.counters[type] -= 1;
        }
    }

    if (baseline.size > 0) {
        throw new Error(
            `${baseline.size} baseline entries are fixed, run \`npm run lint:knip:prune\`:\n${[...baseline].join('\n')}`,
        );
    }

    return data;
};
