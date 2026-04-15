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
├── MAP.md                                  # Metadata Application Profile V1.1
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

The `AddTriplestore` module connects Omeka S to a GraphDB triplestore, enabling SPARQL queries and RDF export of excavation and artefact data.

**Setup:** Copy `graphdb.config.php.dist` to `graphdb.config.php` and fill in your GraphDB credentials.

```
software/omeka-s/modules/AddTriplestore/config/
├── graphdb.config.php.dist   # Template (tracked) — copy and configure
├── graphdb.config.php        # Live config (git-ignored) — never commit
├── module.ini
└── module.config.php
```

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
4. Set permissions and run the Omeka S web installer.

## License

GNU General Public License v3.0 — see [LICENSE](LICENSE).

## References

- [Megalithic Routes](http://www.megalithicroutes.eu/en/megalithic-europe)
- [The Megalithic Portal](http://www.megalithic.co.uk/)
- [5-Star Open Data](https://5stardata.info/en/)
- [Pelagios](https://pelagios.org/)
- [Europeana](https://www.europeana.eu/portal/en)
