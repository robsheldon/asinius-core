#!/bin/bash

# Get canonicalized path to the directory containing this script.
script_dir=$(dirname -- "$(readlink -f -- "${BASH_SOURCE[0]}")")
if [ -z "$script_dir" ]; then
    echo "ERROR: Can't find current directory"
    exit 1
fi

# Locate the unit test directory and make sure it exists.
test_dir=$(readlink -f -- "$script_dir/tests/units")
if [ ! -d "$test_dir" ]; then
    echo "ERROR: Can't find the unit tests directory at \"$test_dir\""
    exit 1
fi

# atoum's colorfied output is incompatible with my shell configuration.
# I find that this tactic produces much nicer output.
# The .atoum.php configuration file also changed in a way that is difficult
# to decipher; this invocation bypasses that.
/usr/bin/php -d xdebug.mode=coverage -r "passthru('XDEBUG_MODE=coverage vendor/bin/atoum --debug -d \"$test_dir\"');"
