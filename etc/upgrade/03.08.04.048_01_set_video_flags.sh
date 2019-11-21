#!/bin/bash

# this upgrade is safe to run on each upgrade and      
# ensures the flags' fields are up to date.            
#   NOTE:  Always add new fields to the END of the     
#   set unless you are replacing another field,        
#   otherwize all the existing flags are thrown off    
#
# see: http://dev.mysql.com/doc/refman/5.0/en/set.html 

FLAGS="'recalc','hidden','locked','moving','current','disabled'"

mysql -u root -pib4exac ezfw <<<"alter table videos modify flags set($FLAGS) default 'recalc';"

