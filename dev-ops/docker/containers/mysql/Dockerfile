FROM mysql:5.7

RUN apt-get update \
  && apt-get install --no-install-recommends -y \
     vim \
     netcat-openbsd

ADD dev.cnf /etc/mysql/conf.d/dev.cnf
ADD remote-access.cnf /etc/mysql/conf.d/remote-access.cnf
ADD performance-schema.cnf /etc/mysql/conf.d/performance-schema.cnf
COPY createuser.sh /tmp/createuser.sh
RUN chmod +rwx /tmp/createuser.sh
RUN /tmp/createuser.sh

COPY grant.sql /docker-entrypoint-initdb.d/grant.sql
