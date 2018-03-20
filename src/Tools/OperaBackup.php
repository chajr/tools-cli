#!/usr/bin/env bash

fileName=`date +"%d.%m.%Y-%T"`
cp "$HOME/Library/Application Support/com.operasoftware.Opera/Last Session" "$HOME/opera-backups/$fileName-last"
cp "$HOME/Library/Application Support/com.operasoftware.Opera/Current Session" "$HOME/opera-backups/$fileName-current"

find $HOME/opera-backups/ -type f -mtime +5d -exec rm {} \;

