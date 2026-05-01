# MegaLOD

**Linked Open Data framework for European Neolithic megalithic heritage**

## About

The cultural heritage sites of the Neolithic period (c. 7000–1700 BCE) have been catalogued by archaeologists, technicians and researchers using isolated tools — spreadsheets, local databases, and closed portals such as [Megalithic Routes](http://www.megalithicroutes.eu/en/megalithic-europe), [The Megalithic Portal](http://www.megalithic.co.uk/), and [The Hunebed Centrum](https://www.hunebedcentrum.eu/). These silos block data reuse, interoperability, and new discoveries.

MegaLOD addresses this by providing:

1. A **Metadata Application Profile (MAP)** that lifts megalithic datasets into [five-star Linked Open Data](https://5stardata.info/en/).
2. **RDF ontologies** (OWL/RDFS classes and properties) and **SKOS controlled vocabularies**, all serialized as Turtle (`.ttl`) files, covering excavation, arrowhead, axe, and loom-weight domains.
3. A **software platform** (Omeka S + custom modules + GraphDB triplestore) for publishing, browsing, and querying the data via SPARQL.

Once published, MegaLOD datasets join the LOD cloud alongside resources from [Pelagios](https://pelagios.org/), [Europeana](https://www.europeana.eu/), and the wider GLAM community.

## Repository Structure

```
MegaLOD/
├── NAMESPACE_POLICY.md                     # Canonical https://purl.org/megalod/ IRIs
├── MAP.md                                  # Metadata Application Profile V1.1
├── scripts/                                # validate_turtle.py, validate_shacl_samples.py
├── .env.example                            # Env var hints (do not commit real .env)
├── .github/workflows/
│   ├── rdf-validation.yml                 # CI: Turtle parse + SHACL samples
│   └── quality-gates.yml                  # CI: AddTriplestore PHP lint, composer audit, Gitleaks
├── metadata-schemes/                       # OWL/RDFS ontology definitions (.ttl)
│   ├── README.md
│   ├── excavation.ttl                      #   Excavation classes and properties
│   ├── arrowhead.ttl                       #   Arrowhead classes and properties
│   └── axe.ttl                             #   Axe classes and properties
├── ves/                                    # SKOS controlled vocabularies (.ttl)
│   ├── README.md
│   ├── MegaLOD-BCAD.ttl                    #   Global: BC/AD
│   ├── MegaLOD-IndexElongation.ttl         #   Global: elongation index
│   ├── MegaLOD-IndexThickness.ttl          #   Global: thickness index
│   ├── ah-*.ttl                            #   Arrowhead vocabularies (8 files)
│   └── axe-*.ttl                           #   Axe vocabularies (6 files)
└── software/
    └── omeka-s/                            # Omeka S application
        ├── modules/
        │   └── AddTriplestore/             #   Custom module (GraphDB integration)
        └── themes/
            └── myTheme/                    #   Custom theme
```

### Folder Ownership

| Folder | Owner |
|--------|----------------|
| `metadata-schemes/` | Ontology team |
| `ves/` | Ontology team |
| `software/` | Development team |
| `MAP.md` | Ontology team |

## Key Custom Module: AddTriplestore

The `AddTriplestore` module connects Omeka S to a **GraphDB** triplestore, enables SPARQL access, and drives upload/validation of excavation and artefact data. **Credentials and base URIs** come from environment variables and `graphdb.config.php`—there are **no insecure defaults** (e.g. no `admin/admin` fallbacks); see the module README for the full list.

**Setup:** Copy `graphdb.config.php.dist` to `graphdb.config.php` and set values or matching env vars. Use root `.env.example` as a checklist for MegaLOD-related variables (`MEGALOD_PUBLIC_BASE_URI`, `GRAPHDB_BASE_URL`, Omeka API keys, etc.); see `modules/AddTriplestore/README.md` for detail.

**Module layout:** Heavy lifting lives in `software/omeka-s/modules/AddTriplestore/src/Service/` (GraphDB HTTP, RDF→Omeka ingestion, REST batch item create, `ExcavationItemSetContextService` for site mappings and location SPARQL, TTL builders `ExcavationTtlBuilder` / `ArrowheadTtlBuilder`, collecting-form mapping). The site `IndexController` orchestrates HTTP and delegates to these services (P1 refactor: thin controller for this slice; further extractions may remain).

```
software/omeka-s/modules/AddTriplestore/config/
├── graphdb.config.php.dist
├── module.ini
└── module.config.php
```

## RDF / ontology quality

- **Canonical IRIs:** `NAMESPACE_POLICY.md` (use `https://` for `purl.org/megalod/`). Published RDF uses those full IRI strings as identifiers; that is independent of where a browser redirect for the bare domain happens to land.
- **PURL vs this repository:** The hostname `purl.org` is administered separately. Opening [https://purl.org/megalod](https://purl.org/megalod) may redirect to a GitHub tree that is **not** this repo. **Maintainers here do not control that redirect.** For the ontology files, MAP, and software in this project, treat **[github.com/basarsy/MegaLOD](https://github.com/basarsy/MegaLOD)** as the source of truth you clone and branch from.
- **CI (data):** On push/PR to `main` / `master` / `develop`, **RDF validation** runs Turtle parsing on `metadata-schemes/` and `ves/`, and SHACL validation of sample graphs against `AddTriplestore/asset/shacl-v1.1/shacl.ttl` (`scripts/validate_turtle.py`, `scripts/validate_shacl_samples.py`).
- **CI (software):** **Quality gates** run PHP syntax checks on `software/omeka-s/modules/AddTriplestore`, `composer audit` (security advisories) for `software/omeka-s`, and **Gitleaks** secret scanning. Failing jobs block merges once those checks are required on the branch.

## Getting Started

1. Clone the repository.
2. Install PHP dependencies:
   ```sh
   cd software/omeka-s
   composer install
   ```
3. Copy config templates and fill in credentials:
   ```sh
   cp config/database.ini.dist config/database.ini
   cp config/local.config.php.dist config/local.config.php
   cp modules/AddTriplestore/config/graphdb.config.php.dist modules/AddTriplestore/config/graphdb.config.php
   ```
   From the **repository root**, you can start from `.env.example` for MegaLOD/GraphDB-related environment variables (never commit a real `.env`).
4. Set permissions and run the Omeka S web installer.

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE).

## References

- [Megalithic Routes](http://www.megalithicroutes.eu/en/megalithic-europe)
- [The Megalithic Portal](http://www.megalithic.co.uk/)
- [5-Star Open Data](https://5stardata.info/en/)
- [Pelagios](https://pelagios.org/)
- [Europeana](https://www.europeana.eu/portal/en)
