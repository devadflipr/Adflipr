#!/usr/bin/env python3
"""
Deduplicate hook capture files under ai/lessons-learned/.

- _hook-capture/*.md: report identical file-content hashes; --apply deletes duplicate files (keeps oldest name).

Run locally or on a schedule (e.g. weekly cron). Does not call any API.

Usage:
  python3 ai/lessons-learned/scripts/dedupe_captures.py          # dry-run, print report
  python3 ai/lessons-learned/scripts/dedupe_captures.py --apply # delete duplicate md captures
"""
from __future__ import annotations

import argparse
import hashlib
from pathlib import Path


def repo_root() -> Path:
    here = Path(__file__).resolve()
    for p in [here] + list(here.parents):
        if (p / ".git").is_dir():
            return p
    return here.parents[2]


def hook_capture_dir(root: Path) -> Path:
    return root / "ai" / "lessons-learned" / "_hook-capture"


def dedupe_markdown(root: Path, apply: bool) -> tuple[int, list[str]]:
    d = hook_capture_dir(root)
    if not d.is_dir():
        return 0, []
    by_hash: dict[str, list[Path]] = {}
    for f in sorted(d.glob("*.md")):
        content = f.read_bytes()
        h = hashlib.sha256(content).hexdigest()
        by_hash.setdefault(h, []).append(f)

    removed = 0
    actions: list[str] = []
    for h, paths in by_hash.items():
        if len(paths) <= 1:
            continue
        ordered = sorted(paths, key=lambda p: p.name)
        keep = ordered[0]
        for extra in ordered[1:]:
            actions.append(f"dup md hash={h[:12]}… keep={keep.name} drop={extra.name}")
            if apply:
                extra.unlink()
                removed += 1
    return removed, actions


def main() -> None:
    ap = argparse.ArgumentParser(description="Dedupe lessons-learned capture files.")
    ap.add_argument("--apply", action="store_true", help="Delete duplicate .md files")
    args = ap.parse_args()
    root = repo_root()

    print(f"repo: {root}")
    rm, actions = dedupe_markdown(root, args.apply)
    if actions:
        print("Markdown duplicates:")
        for a in actions[:50]:
            print(" ", a)
        if len(actions) > 50:
            print(f"  … and {len(actions) - 50} more")
    print(f"Markdown files removed: {rm} apply={args.apply}")


if __name__ == "__main__":
    main()
