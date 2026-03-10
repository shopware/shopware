## MANDATORY: Vagrant Command Execution Override

**CRITICAL: ALL commands MUST be executed inside Vagrant VM**
**FORBIDDEN: Direct host execution - will cause failures**

### Required Execution Pattern:
```bash
vagrant ssh -c "cd /vagrant && <command>"
```

### Examples:
- `vagrant ssh -c "cd /vagrant && composer ecs-fix"`
- `vagrant ssh -c "cd /vagrant && bin/console cache:clear"`

**All commands from AGENTS.md must be wrapped with the Vagrant SSH pattern.**

**Working directory:** Always include `cd /vagrant &&` to ensure correct path