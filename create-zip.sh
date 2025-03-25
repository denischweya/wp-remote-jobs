#!/bin/bash

# Navigate to parent directory
cd ..

# Create zip file excluding development files
zip -r remote-jobs.zip remote-jobs \
    -x "remote-jobs/node_modules/*" \
    -x "remote-jobs/.git/*" \
    -x "remote-jobs/includes/blocks/*/node_modules/*" \
    -x "remote-jobs/**/.DS_Store" \
    -x "remote-jobs/.gitignore" \
    -x "remote-jobs/package-lock.json" \
    -x "remote-jobs/create-zip.sh" 