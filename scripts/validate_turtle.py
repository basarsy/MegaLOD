#!/usr/bin/env python3
"""Parse all canonical Turtle in metadata-schemes/ and ves/. Exit non-zero on failure."""
from __future__ import annotations

import os
import sys

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

try:
    from rdflib import Graph
except ImportError:
    print("rdflib is required: pip install -r scripts/requirements-rdf.txt", file=sys.stderr)
    sys.exit(2)


def iter_ttl_dirs() -> list[str]:
    return [
        os.path.join(REPO_ROOT, "metadata-schemes"),
        os.path.join(REPO_ROOT, "ves"),
    ]


def main() -> int:
    paths: list[str] = []
    for d in iter_ttl_dirs():
        if not os.path.isdir(d):
            print(f"Missing directory: {d}", file=sys.stderr)
            return 1
        for name in sorted(os.listdir(d)):
            if not name.endswith(".ttl"):
                continue
            paths.append(os.path.join(d, name))

    errors: list[tuple[str, str]] = []
    for p in paths:
        g = Graph()
        try:
            g.parse(p, format="turtle")
        except Exception as e:  # noqa: BLE001 — surface parse error text
            errors.append((p, str(e)))

    if errors:
        for p, msg in errors:
            print(f"FAIL {p}\n  {msg}", file=sys.stderr)
        print(f"\n{len(errors)} file(s) failed to parse.", file=sys.stderr)
        return 1

    print(f"OK: parsed {len(paths)} Turtle file(s) under metadata-schemes/ and ves/.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
