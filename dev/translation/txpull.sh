#!/bin/bash
#------------------------------------------------------
# Script to pull language files to Transifex
#
# Laurent Destailleur - eldy@users.sourceforge.net
#------------------------------------------------------
# Usage: txpull.sh (all|xx_XX) [-r module.file] [-f]
#------------------------------------------------------

DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd $DIR/../..

# Syntax
if [ "x$1" = "x" ]
then
	echo "This pull remote transifex files to local dir."
	echo "Note:  If you pull a language file (not source), file will be skipped if local file is newer."
	echo "       Using -f will overwrite local file (does not work with 'all')."
	echo "Usage: ./dev/translation/txpull.sh (all|xx_XX) [-r module.file] [-f] [-s]"
	exit
fi

if [ ! -d ".tx" ]
then
	echo "Script must be ran from root directory of project with command ./dev/translation/txpull.sh"
	exit
fi


if [ "x$1" = "xall" ]
then
	for dir in `find htdocs/*/langs/* -type d | cut -d '/' -f 4 | sort -u`
	do
	    fic=$dir
	    if [ $fic != "en_US" ]
	    then
		    echo "tx pull -l $fic $2 $3"
		    tx pull -l $fic $2 $3
		fi
	done
	cd -
else
	echo "tx pull -l $1 $2 $3 $4 $5"
	tx pull -l $1 $2 $3 $4 $5
fi

echo Think to launch also: 
echo "> dev/fixaltlanguages.sh fix all"
