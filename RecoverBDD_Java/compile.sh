#!/bin/bash
mkdir -p bin
LIBS=$(find lib -name "*.jar" | paste -sd ":" -)
javac -cp "$LIBS" -d bin src/Main.java
