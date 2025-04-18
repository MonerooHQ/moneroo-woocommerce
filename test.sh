#!/bin/bash

# Define the name of the ZIP file
PLUGIN="moneroo"
VERSION="1.0.0".`date +%Y%m%d%H%M%S`
ZIP_FILE="builds/$PLUGIN-$VERSION.zip"

#Install JS dependencies
npm install

# Build the JS assets
npm run build

# Build Plugin
zip -r $ZIP_FILE . -x "build-cfg/*" "builds/*" ".*" "wp-assets/*" "node_modules/*" "phpunit.xml.dist" "unused-scanner.php" "README.md" ".php-cs-fixer.dist.php" "phpstan.neon" "phpunit.xml" "src/index.js" "pnpm-lock.yaml" "test.sh" "package-lock.json" "package.json"

# Create the builds directory if it doesn't exist
mkdir -p builds

# Check if the ZIP file was created successfully
if [ -f "$ZIP_FILE" ]; then
  echo "Plugin file $ZIP_FILE successfully built"
else
  echo "Built zip file $ZIP_FILE does not exist" 1>&2
  exit 1
fi