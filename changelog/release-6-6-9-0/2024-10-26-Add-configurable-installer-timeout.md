---
title: Add configurable installer timeout
issue: NEXT-39312
author: Marc Christenfeldt
author_email: marc.christenfeldt@gmx.de
author_github: marcbln
discussion: https://forum.shopware.com/t/shopware-update-bricht-wegen-zu-langer-laufzeit-ab/104013/11
---

# Core

* Added new environment variable `SHOPWARE_INSTALLER_TIMEOUT` to configure the command timeout in the web installer
* Added constant `DEFAULT_TIMEOUT` in `StreamedCommandResponseGenerator` to define the default timeout value of 900 seconds
* Changed timeout handling in `StreamedCommandResponseGenerator` to support configurable timeout values from environment
___
# Upgrade Information

## Environment Configuration

The web installer now supports configurable command timeouts through the environment variable `SHOPWARE_INSTALLER_TIMEOUT`. This value should be provided in seconds.

### Default Behavior
If the environment variable is not set, the installer will use the default timeout of 900 seconds (15 minutes).

### Configuration Examples
```bash
# Set timeout to 30 minutes
export SHOPWARE_INSTALLER_TIMEOUT=1800

# Set timeout to 1 hour
export SHOPWARE_INSTALLER_TIMEOUT=3600
```

Or in the projects' `.env.installer` file:

```bash
SHOPWARE_INSTALLER_TIMEOUT=1800
```

### Validation
The provided timeout value must be:
- A numeric value
- Non-negative

If these conditions are not met, the installer will fall back to the default timeout of 900 seconds.
