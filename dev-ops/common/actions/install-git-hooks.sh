#!/usr/bin/env bash
#DESCRIPTION: install git pre commit hook

I: mkdir .git/hooks
ln -s -r -f build/gitHooks/pre-commit .git/hooks/pre-commit
