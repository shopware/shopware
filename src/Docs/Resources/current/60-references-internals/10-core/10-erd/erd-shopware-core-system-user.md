[titleEn]: <>(Admin Accounts)
[hash]: <>(article:internals_core_erd_system_user)

[Back to modules](./../10-modules.md)

Account management of administration users.

![Admin Accounts](./dist/erd-shopware-core-system-user.png)


### Table `user`

Stores account details associated with an administration user.


### Table `user_config`

Saving config of user.


### Table `user_access_key`

Stores the oAuth access for a specific account to the admin.


### Table `user_recovery`

Simple M:N association related to the password recovery process.


[Back to modules](./../10-modules.md)
