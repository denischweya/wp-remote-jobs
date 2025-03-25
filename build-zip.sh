#!/bin/bash

# Display what we're doing
echo "Building plugin ZIP file..."

# Navigate to the plugin directory (in case script is run from elsewhere)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" &>/dev/null && pwd)"
cd "$SCRIPT_DIR"

# First build the blocks
echo "Building blocks..."
npx wp-scripts build includes/blocks/*/src/index.js --output-path=includes/blocks/*/build

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
    -x "remote-jobs/package-lock.json" \
    -x "remote-jobs/create-zip.sh" \
    -x "remote-jobs/build-zip.sh"

# Verify the zip was created
if [ -f "remote-jobs.zip" ]; then
    echo "✅ ZIP file created successfully: $(pwd)/remote-jobs.zip"
else
    echo "❌ Failed to create ZIP file"
    exit 1
fi 