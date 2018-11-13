#!/bin/bash
log_day=`date -d '-1 day' +%Y-%m-%d`
cd ~/ddzh_swoole/logs
FILES=$(ls $log_day*.txt 2>/dev/null)--
if [ "$FILES" ]; then
    tar cvzf "$log_day.tar.gz" $log_day*.txt --remove-files
fi
