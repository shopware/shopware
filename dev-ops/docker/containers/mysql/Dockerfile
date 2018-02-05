FROM mysql:5.7

# copy mysql config
ADD dev.cnf /etc/mysql/conf.d/dev.cnf
ADD remote-access.cnf /etc/mysql/conf.d/remote-access.cnf
ADD performance-schema.cnf /etc/mysql/conf.d/performance-schema.cnf

COPY grant.sql /docker-entrypoint-initdb.d/grant.sql
