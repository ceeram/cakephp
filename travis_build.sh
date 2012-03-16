#!/usr/bin/env bash

WRONG="NOT RUNNING CORRECT COMMAND!"
if [[ "$TRAVIS_PHP_VERSION" == "5.2" ]]
then
    echo $TRAVIS_PHP_VERSION
    ./lib/Cake/Console/cake test core AllTests --exclude-group stderr
else
    echo $WRONG
    ./lib/Cake/Console/cake test core AllTests --stderr
fi
