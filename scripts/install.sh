#!/bin/bash

# geekbench controller
CTL="${BASEURL}index.php?/module/geekbench/"

# Get the scripts in the proper directories
"${CURL[@]}" "${CTL}get_script/geekbench" -o "${MUNKIPATH}preflight.d/geekbench"

# Check exit status of curl
if [ $? = 0 ]; then
	# Make executable
	chmod a+x "${MUNKIPATH}preflight.d/geekbench"

	# Set preference to include this file in the preflight check
	setreportpref "geekbench" "${CACHEPATH}geekbench.plist"

else
	echo "Failed to download all required components!"
	rm -f "${MUNKIPATH}preflight.d/geekbench"

	# Signal that we had an error
	ERR=1
fi