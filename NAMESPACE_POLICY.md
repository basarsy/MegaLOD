# MegaLOD namespace policy (canonical)

This document locks **IRI identity**. RDF 1.1 compares IRIs by string equality; `http://` and `https://` are different resources unless you publish explicit same-As or redirects.

## Rules

1. **Scheme:** Use **`https://`** for all MegaLOD IRIs under `purl.org/megalod/`.
2. **Metadata schemes (OWL/RDFS):** Base paths:
   - `https://purl.org/megalod/ms/excavation/` — prefix `excav:`
   - `https://purl.org/megalod/ms/ah/` — prefix `ah:`
   - `https://purl.org/megalod/ms/axe/` — prefix `axe:`
3. **SKOS concept schemes:** `https://purl.org/megalod/kos/<scheme-local-name>` (scheme IRIs and concept IRIs as defined in each `ves/*.ttl` file). For **MegaLOD-IndexElongation** and **MegaLOD-IndexThickness**, concept path segments are **lowercase** (e.g. `/elongated`, `/medium`) so they match Omeka export code and SHACL enumerations.
4. **Authority:** The authoritative definitions are the Turtle files in `metadata-schemes/` and `ves/`. READMEs, `MAP.md`, Omeka templates, and PHP that emit triples must use the **same** IRIs and **local names** as those files.
5. **Editorial changes:** Vocabulary or ontology changes that alter IRIs require a version note (and external redirect strategy if IRIs were already published).

## Validation

CI runs Turtle parsing on all `metadata-schemes/*.ttl` and `ves/*.ttl`. SHACL shapes for application data live under `software/omeka-s/modules/AddTriplestore/asset/shacl-v1.1/` and are validated separately when that module’s assets change.
