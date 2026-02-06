#!/bin/bash
cd "$(dirname "$0")"
LIBS=$(find lib -name "*.jar" | paste -sd ":" -)
java -cp "bin:$LIBS" Main
