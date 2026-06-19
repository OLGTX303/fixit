#!/usr/bin/env bash
set -euo pipefail
echo "Frontend: cd $(dirname "$0")/.. && npm run dev"
echo "Backend:  cd ../fixit-backend && composer start"
echo "Ensure MySQL is running and schema.sql + seed.sql are imported."