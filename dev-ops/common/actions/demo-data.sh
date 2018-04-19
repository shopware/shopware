#!/usr/bin/env bash
#DESCRIPTION: creates a demo data set

bin/console framework:demodata --products=500 --categories=5 --manufacturers=25 -eprod --tenant-id=ffffffffffffffffffffffffffffffff
