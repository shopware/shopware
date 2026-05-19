## MANDATORY: Docker Command Execution Override

**CRITICAL: ALL commands MUST be executed inside Docker containers**
**FORBIDDEN: Direct host execution - will cause failures**

### Required Execution Pattern:
```bash
docker exec -it shopware_app <command>    # PHP/Composer/Console commands
docker exec -it shopware_node <command>   # Node/NPM commands
```

### Examples:
- `docker exec -it shopware_app composer ecs-fix`
- `docker exec -it shopware_app bin/console cache:clear`

**All commands from AGENTS.md must be prefixed with the appropriate docker exec pattern.**

**Container names:** `shopware_app` (PHP), `shopware_node` (Node) - verify with `docker ps`