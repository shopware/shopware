# 2021-08-11 - Make shopware/platform stand-alone for development and testing

## Context

The platform requires some additional config, a console and web entrypoint and additional development tooling for development, tests and
running the application. In practice this is provided by one of the templates: `shopware/development` or `shopware/production`. 
This creates a cyclic dependency, which brings some problems:
- `shopware/development` and `shopware/platform` need to be updated in lockstep, which makes updating them individually sometimes impossible 
- some IDEs have trouble with multi repository projects
- updating development tooling breaks everything
- auto-detection of git revision and diff is broken, because the development template is the root
- for each release branch an additional branch needs to be maintained

## Decision

- use shopware/platform directly in the pipeline
- allow development without a template, by moving the development tooling into platform
- only advertise this as `shopware/platform` development setup. Projects should still start with `shopware/production` as a template
- `shopware/development` should continue to work
- allow testing by adding entrypoints for cli and web
- add scripts to composer to ease common tasks
  * these scripts should be kept small and simple
  * essential functionality should be implemented as npm scripts or symfony commands  
  * we should improve the symfony commands or npm scripts if they are too complicated
  * if possible the scripts should allow adding arguments
- use standard convention
  * `.env.dist` provides default environment variables
  * `.env` can be used to define a custom environment (for example, if you use a native setup)
  * `docker-compose.yml` provides a working environment
  * `docker-compose.override.yml` can be used for local overrides to expose ports for example
- use defaults that work out of the box in most cases
  * don't expose hard coded ports in docker-compose.yml. It's not possible to undo it and may prevent startup of the app service

## Consequences

- simplified CI, which also makes errors easier to reproduce locally
- simplified local setup  
- no custom scripts, that are not available in all setups
- projects may try to use shopware/platform directly
- yet another shopware setup to choose from
