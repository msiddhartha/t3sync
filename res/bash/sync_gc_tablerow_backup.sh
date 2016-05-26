#!/bin/bash
#
# Shell script 
#
#
# $1: 
# $2: 
# $3:

echo $1 

mysqldump --skip-add-drop-table -c -t -w"$1" -uroot -proot -hlocalhost iris tx_st9fissync_dbversioning_query > /var/kordoba/gc_st9fissync/2013/3/20/gc1-1363777679-tx_st9fissync_dbversioning_query_10.sql

res="$?"
echo $res

#Check for error
if [ $res = 0 ]
then
echo "MySQL was dumped successfully"     
else
echo "Error occured"
fi

exit $res
