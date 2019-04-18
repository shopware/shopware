[titleEn]: <>(Startup guide)

Now that you have the Shopware Platform up and running, what can you do with it?

## User Interfaces

From an outside view, how can you make the Shopware Platform do stuff?

### Web Access

At first you might want to take a glance at the new Shopware Platform through your browser. There is a customer frontend *(the Storefront)* you can access through your host directly: 

Storefront: `http://your_host_setting/`

And there is an administration overview that you can access by adding `/admin` to your host.

Administration: `http://your_host_setting/admin`

The default user and password are `admin` and `shopware`.

### Console Access

The next user interface you should try is the command line interface. Here we have two different modes of access. Either `console` or `psh.phar`. Just open a terminal and change your directory to the path you installed the Shopware Platform to.

#### PSH

PSH-Scripts live outside the application and add a convenience layer to many maintenance tasks.

To get a full list of all supported commands execute:

```bash
> ./psh.phar
```

This will print a list of all actions with descriptions. *Please note that some actions are context dependent. For example the `docker` actions are only working if you use the docker setup.*

All default actions can be triggered by just providing the name of the action. So if you want to reinstall the Shopware Platform just type:

```bash
> ./psh.phar install
```

Or if you want to rebuild the administration specifically just type:

```bash
> ./psh.phar administration:build
```

As a default PSH-Scripts do not need additional arguments to work - so the action name is always sufficient. 

#### Symfony Console

The next interface is the `console`. This represents maintenance tasks of the various functions of the Shopware Platform. To get started execute: 

```bash
> bin/console
```

This prints a list of all available commands. All commands of the Shopware Platform are of the format `namespace:action` and require various parameters or options.

Executing a single command looks like this:

```
bin/console foo:bar
```

To get help information about required and optional parameters, you can always suffix your call with `--help` to get a full list of supported commands.

## The sources

Now that you have a idea how to execute the various Shopware Platform stacks, we will briefly point you to the sources directories. But keep in mind that there are entire documents describing the [directory structure](./../../2-internals/110-directory-structure/__categoryInfo.md) of the Shopware Platform.

| Thing | Place
| ----: | :----
| Backend | `platform/src/Core`
| Storefront  | `platform/src/Storefront`
| Storefront template  | `platform/src/Storefront/Resources`
| Administration | `platform/src/Administration/`
| Administration application | `platform/src/Administration/Resources/administration`

## And now?

Look around! Add products, trigger orders, register customers and use the Shopware Platform. Maybe you want to add debug statements to some sources to get a deeper insight and if you are ready to learn more hop over to the [internals](./../../2-internals/__categoryInfo.md).
