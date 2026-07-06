## Native/WSL Command Execution

**For native Linux, macOS, or WSL environments with local PHP/Node installation**

### Direct Execution Pattern:
Execute all commands directly as shown in AGENTS.md without any prefix.

### Examples:
- `composer ecs-fix`
- `bin/console cache:clear`

**All commands from AGENTS.md can be executed directly without modification.**

**Requirements:**
- PHP 8.2+ installed locally
- Composer installed locally
- Node.js installed locally
- All dependencies accessible from host system