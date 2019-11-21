#!/bin/bash

source /usr/local/ezfw/etc/common-install-functions

# add the date_records table
cv_installSQL ezfw root ib4exac /usr/local/ezfw/etc/sql/tables/date_records.sql || echo "table already installed";

# populate it
cv_installSQL ezfw root ib4exac /usr/local/ezfw/etc/upgrade/03.09.00.299_01.sql || echo "failed populating date_records table";

# create triggers
cv_installSQL ezfw root ib4exac /usr/local/ezfw/etc/sql/triggers/date_records.sql || echo "failed installing triggers for date_records";

