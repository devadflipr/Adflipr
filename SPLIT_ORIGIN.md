# Split origin — adflipr-wordpress

History-preserving extract from the Adflipr monorepo (`wordpress/` → repo root).

## Method
- `git filter-repo` kept: `wordpress/` (promoted to root), `.cursor/`, `ai/`
- Stripped sensitive paths if present (`.env.deploy`, etc.)

## Not included
- Backend `service/`, frontend `adflipr-app/`, Shopify app
- Local build artifacts (`node_modules/`, `*.zip`) — gitignored
