#!/bin/bash
set -e

REMOTE_HOST="dev-root"
REMOTE_PATH="/var/www/bp-play.demostatus.com/htdocs/wp-content/plugins/humata-chatbot"

echo "🚀 Deploying humata-chatbot to staging..."

rsync -avz --delete \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude="node_modules" \
  --exclude=".DS_Store" \
  --exclude=".env" \
  --exclude="README.md" \
  ./ \
  "${REMOTE_HOST}:${REMOTE_PATH}/"

echo "✅ Deployment complete!"
echo "📍 Remote path: ${REMOTE_PATH}"