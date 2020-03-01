#!/bin/sh

cp -f install/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit
echo "Copying pre-commit file"
