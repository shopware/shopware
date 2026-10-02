#!/usr/bin/env node
import { diff } from './commands/diff.ts';
import { info } from './commands/info.ts';
import { seed } from './commands/seed.ts';
import { swap } from './commands/swap.ts';

const USAGE = `shot — sw-screenshot run helpers

  shot info                 print the shop URL, admin credentials and paths
  shot seed <script.ts>     run a seed script with TestDataService pre-wired
  shot swap <head|base>     repoint the shop at the other ref and bring it back up
  shot diff <a> <b> [out]   heat map of what changed between two screenshots
`;

/**
 * Dispatch a CLI subcommand.
 *
 * Exported rather than inlined so the CLI tests can drive it without spawning a process.
 */
export async function main(argv: string[]): Promise<number> {
    const [command, ...rest] = argv;

    try {
        switch (command) {
            case 'info':
                info();
                break;
            case 'seed':
                await seed(rest);
                break;
            case 'swap':
                swap(rest);
                break;
            case 'diff':
                diff(rest);
                break;
            default:
                process.stderr.write(USAGE);

                return command === undefined || command === '--help' ? 0 : 1;
        }
    } catch (error) {
        process.stderr.write(`${error instanceof Error ? error.message : String(error)}\n`);

        return 1;
    }

    return 0;
}

process.exitCode = await main(process.argv.slice(2));
