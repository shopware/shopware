[titleEn]: <>(Using docker-sync)
[hash]: <>(article:docker_installation)


## Using native mounting with Docker Volumes and docker-sync

If you are working with Mac/OSX and are facing performance issues, you should use [docker-sync](http://docker-sync.io/) instead of the default mounting strategy.

### Preparation

Download & install `docker-sync` from [http://docker-sync.io/](http://docker-sync.io/), which supports OSX, Windows, Linux and FreeBSD.
`docker-sync` uses Ruby, which is pre-installed on OSX. On other operating systems, you might have to [install Ruby](https://www.ruby-lang.org/en/) separately.

* For OSX, see [OSX](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-osx).
* For Windows, see [Windows](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-windows).
* For Linux, see [Linux](https://docker-sync.readthedocs.io/en/latest/getting-started/installation.html#installation-linux).
* See the list of alternatives [here](https://docker-sync.readthedocs.io/en/latest/miscellaneous/alternatives.html)


### Enable the use of docker-sync in PSH Console

By default, the usage of `docker-sync` is disabled in PSH. To use Docker Volumes with Docker Sync, you must set `DOCKER_SYNC_ENABLED`  to `true` in your `.psh.yaml.override`. Create a new entry in the `const` section like so:

```yaml
const:
  #..
  DOCKER_SYNC_ENABLED: true
```

That's it. Continue to install Shopware 6 as usual:

1. Build and start the containers:

    ```bash
    > ./psh.phar docker:start
    ```

> This command creates and starts the containers, watchers, and the sync itself. Running start the first time takes several minutes to complete.
> Subsequent starts are a lot faster since the images and volumes are reused.

2. Access the application container:

    ```bash
    > ./psh.phar docker:ssh
    ```

3. Execute the installer inside the Docker container:

    ```bash
    > ./psh.phar install 
    ```

For more information about Shopware Installation, take a look [here](https://docs.shopware.com/en/shopware-platform-dev-en/getting-started/system-installation-guides)
  
