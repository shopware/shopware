## MANDATORY: DDEV Command Execution Override

**CRITICAL: ALL commands MUST be executed through DDEV**
**FORBIDDEN: Direct host execution - will cause failures**

### Required Execution Pattern:
```bash
ddev composer <command>          # Composer commands
ddev exec bin/console <command>  # Console commands
ddev exec <path/to/binary>       # Binary executables
ddev npm <command>               # NPM commands
```

### Examples:
- `ddev composer ecs-fix`
- `ddev exec bin/console cache:clear`

**All commands from AGENTS.md must be adapted to use the appropriate DDEV pattern.**

**Quick Rule:** Use `ddev composer` for composer, `ddev npm` for npm, `ddev exec` for binaries and console