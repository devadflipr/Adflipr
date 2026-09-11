# Infrastructure — SSH host aliases

**Purpose:** Quick reference for **production** and **staging** servers when using `~/.ssh/config` host aliases. Full DigitalOcean inventory: `docs/INFRA_ADFLIPR_PROD.md`, `docs/INFRA_ADFLIPR_STAGING.md`.

**GitLab CI/CD defaults:** SSH user **`khushal`**, repo **`/home/khushal/adflipr`** — see `docs/GITLAB_CICD.md`.

## Production

| Alias | IP | User | Role |
|-------|-----|------|------|
| **nginx-prod** | `188.166.255.56` | root | Nginx / reverse proxy (production); use **`~/.ssh/id_ed25519_adflipr_prod`** — see `docs/SSH-CONFIG-ADDITIONS.md` |
| **fe-prod** | `146.190.82.102` | khushal | Frontend (production) |
| **app-prod** | `157.245.151.62` | khushal | Application (production) |

```bash
ssh root@188.166.255.56   # prod nginx
ssh fe-prod
ssh app-prod
```

**Also in prod infra:** main **app** droplet `138.197.63.124` (often `Host app` in SSH config) — see prod infra doc.

## Staging

| Alias | IP | User | Role |
|-------|-----|------|------|
| **fe-dev** | `188.166.244.53` | khushal | GitLab Runner + dev frontend + dev backend (CI `DEV_HOST`) |
| **app-dev** | `157.245.51.132` | khushal | Legacy app droplet (**offline**; CI does not deploy here) |

```bash
ssh fe-dev    # primary staging host (188.166.244.53)
ssh app-dev   # legacy / offline only
```

## Source of truth

- Canonical infra + droplets: **`docs/INFRA_ADFLIPR_PROD.md`**, **`docs/INFRA_ADFLIPR_STAGING.md`**
- GitLab pipeline + deploy: **`docs/GITLAB_CICD.md`**, **`docs/STAGING_GITLAB_RUNNER_SETUP.md`**
- Local SSH entries: **`~/.ssh/config`** on each machine (`IdentityFile` per host; **prod nginx SGP1** requires **`id_ed25519_adflipr_prod`** for `root@188.166.255.56` — do not confuse with a different host alias named `prod-nginx` if that points at another IP in your personal config)
