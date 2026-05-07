#!/bin/bash
set -e

# Build script for OpenBiblio production Docker image
# Usage: ./build-production.sh [version]
# Example: ./build-production.sh 1.0.0

VERSION=${1:-latest}

echo "Building OpenBiblio production image..."
echo "Version: $VERSION"
echo ""

# Build the production image
docker build \
  --target production \
  --tag openbiblio:${VERSION} \
  --tag openbiblio:latest \
  .

echo ""
echo "✓ Build complete!"
echo ""
echo "Image tags:"
echo "  - openbiblio:${VERSION}"
echo "  - openbiblio:latest"
echo ""
echo "To deploy, update VERSION=${VERSION} in .env.prod and run:"
echo "  docker compose -f docker-compose.prod.yml --env-file .env.prod up -d"
echo ""
