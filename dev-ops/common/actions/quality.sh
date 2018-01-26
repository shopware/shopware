#!/usr/bin/env bash
#DESCRIPTION: Run code quality analyzes

./deptrac.phar analyze --formatter-graphviz=0 architecture.depfile.yml
./deptrac.phar analyze --formatter-graphviz=0 api.depfile.yml

I: ./phpstan.phar analyse -l 4 -c phpstan.neon --autoload-file=./vendor/autoload.php src/*