#!/bin/bash

# Remove geekbench script
rm -f "${MUNKIPATH}preflight.d/geekbench"

# Remove geekbench.plist cache file
rm -f "${MUNKIPATH}preflight.d/cache/geekbench.plist"
rm -f "${MUNKIPATH}preflight.d/cache/geekbench.txt"