#!/usr/bin/env python3
"""Validate representative instance graphs against AddTriplestore SHACL shapes."""
from __future__ import annotations

import os
import sys

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SHACL_DIR = os.path.join(
    REPO_ROOT,
    "software",
    "omeka-s",
    "modules",
    "AddTriplestore",
    "asset",
    "shacl-v1.1",
)
SHAPES = os.path.join(SHACL_DIR, "shacl.ttl")
SAMPLES = [
    "valid-excav-copy.ttl",
    "valid-excav-arrow.ttl",
]


def main() -> int:
    try:
        import pyshacl
        from rdflib import Graph
    except ImportError:
        print("Requires: pip install -r scripts/requirements-rdf.txt", file=sys.stderr)
        return 2

    if not os.path.isfile(SHAPES):
        print(f"Missing SHACL shapes: {SHAPES}", file=sys.stderr)
        return 1

    shapes = Graph()
    shapes.parse(SHAPES, format="turtle")

    failed = 0
    for name in SAMPLES:
        path = os.path.join(SHACL_DIR, name)
        if not os.path.isfile(path):
            print(f"SKIP missing {path}", file=sys.stderr)
            continue
        data = Graph()
        data.parse(path, format="turtle")
        conforms, _, report_text = pyshacl.validate(
            data_graph=data,
            shacl_graph=shapes,
            inference=None,
            abort_on_first=False,
        )
        if conforms:
            print(f"OK SHACL: {name}")
        else:
            failed += 1
            print(f"FAIL SHACL: {name}\n{report_text}", file=sys.stderr)

    if failed:
        return 1
    print(f"OK: SHACL validation passed for {len(SAMPLES)} sample file(s).")
    return 0


if __name__ == "__main__":
    sys.exit(main())
