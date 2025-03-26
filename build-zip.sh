#!/bin/bash

# Display what we're doing
echo "Building plugin ZIP file..."

# Navigate to the plugin directory (in case script is run from elsewhere)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &>/dev/null && pwd)"
cd "$SCRIPT_DIR"

# Skip the webpack build since we already have built files
# Uncomment the below if you want to rebuild blocks
#echo "Building blocks..."
#for block_dir in includes/blocks/*/
#do
#  if [ -f "${block_dir}src/index.js" ]; then
#    echo "Building ${block_dir}..."
#    npx wp-scripts build "${block_dir}src/index.js" --output-path="${block_dir}build"
#  fi
#done

# Navigate to parent directory
cd ..

# Create zip file excluding development files
echo "Creating ZIP file..."
zip -r remote-jobs.zip remote-jobs \
    -x "remote-jobs/node_modules/*" \
    -x "remote-jobs/.git/*" \
    -x "remote-jobs/includes/blocks/*/node_modules/*" \
    -x "remote-jobs/**/.DS_Store" \
    -x "remote-jobs/.gitignore" \
    -x "remote-jobs/.gitattributes" \
    -x "remote-jobs/.npmrc" \
    -x "remote-jobs/includes/blocks/*/.gitignore" \
    -x "remote-jobs/includes/blocks/*/.editorconfig" \
    -x "remote-jobs/package-lock.json" \
    -x "remote-jobs/create-zip.sh" \
    -x "remote-jobs/build-zip.sh" \
    -x "remote-jobs/run-build.sh" \
    -x "remote-jobs/**/.*" \
    -x "remote-jobs/**/.*/.*"

# Verify the zip was created
if [ -f "remote-jobs.zip" ]; then
    echo "✅ ZIP file created successfully: $(pwd)/remote-jobs.zip"
    echo "The following files have been excluded:"
    echo "  - All hidden files (.*)"
    echo "  - node_modules directories"
    echo "  - package-lock.json"
    echo "  - Build scripts"
else
    echo "❌ Failed to create ZIP file"
    exit 1
fi 