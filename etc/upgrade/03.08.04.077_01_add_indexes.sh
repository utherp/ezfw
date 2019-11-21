#!/bin/bash

# this update adds indexes to the videos, states 
# and events tables to speed up queries.
# --Stephen

source /usr/local/ezfw/etc/common-install-functions

cv_installSQL ezfw root ib4exac /usr/local/ezfw/etc/upgrade/03.08.04.077_01.sql

