# MegaLOD Project Metadata Schemes

These metadata schemes support the MegaLOD Metadata Application Profile (see `MAP.md` and [the MAP overview](https://purl.org/megalod/map)). **Canonical IRIs** are defined in the `.ttl` files; see `NAMESPACE_POLICY.md` at the repository root.

# Excavation Metadata Scheme

**Namespace:** `https://purl.org/megalod/ms/excavation/` (prefix `excav:`)

## Classes

| Label                                   | Vocabulary Term         | Note                                                             |
|-----------------------------------------|-------------------------|------------------------------------------------------------------|
| Excavation                              | Excavation              | rdfs:subClassOf crmarchaeo:A9_Archaeological_Excavation          |
| Archaeologist                           | Archaeologist           | rdfs:subClassOf foaf:Person; owl:equivalentClass crm:E21_Person  |
| Location                                | Location                | rdfs:subClassOf dbo:Place                                        |
| GPS Coordinates                         | GPSCoordinates          | rdfs:subClassOf geo:SpatialThing                                 |
| Encounter Event                         | EncounterEvent          | rdfs:subClassOf crmsci:S19_Encounter_Event                       |
| Stratigraphic Volume Unit               | StratigraphicVolumeUnit | rdfs:subClassOf crmarchaeo:A2_Stratigraphic_Volume_Unit          |
| Context                                 | Context                 | rdfs:subClassOf crmarchaeo:A1_Excavation_Processing_Unit         |
| TimeLine                                | TimeLine                | rdfs:subClassOf time:TemporalEntity                              |
| Instant                                 | Instant                 | rdfs:subClassOf time:Instant                                     |
| Square                                  | Square                  | rdfs:subClassOf schema:Place                                     |
| Coordinates                             | Coordinates             | rdfs:subClassOf schema:GeoCoordinates                            |
| Weight                                  | Weight                  | rdfs:subClassOf schema:QuantitativeValue                        |
| Depth                                   | Depth                   | rdfs:subClassOf schema:QuantitativeValue                        |
| TypometryValue                          | TypometryValue          | rdfs:subClassOf schema:QuantitativeValue                        |
| Item                                    | Item                    | rdfs:subClassOf crm:E24_Physical_Man-Made_Thing                 |

## Terms

| Label                                   | Vocabulary Term                  | Domain                        | Range                             | VES |
|-----------------------------------------|----------------------------------|-------------------------------|-----------------------------------|-----|
| Has GPS Coordinates                     | hasGPSCoordinates                | Location                      | GPSCoordinates                    |     |
| Has person in charge                    | hasPersonInCharge                | Excavation                    | Archaeologist                     |     |
| Has context                             | hasContext                       | Excavation                    | Context                           |     |
| has Stratigraphic Unit                  | hasSVU                           | Context                       | StratigraphicVolumeUnit           |     |
| Has Square                              | hasSquare                        | Square                        | Excavation                        |     |
| hasTimeLine                             | hasTimeline                      | StratigraphicVolumeUnit       | TimeLine                          |     |
| Item found in a StratigraphicVolumeUnit | foundInSVU                       | EncounterEvent                | StratigraphicVolumeUnit           |     |
| Item found in a Context                 | foundInContext                   | EncounterEvent                | Context                           |     |
| Item found in a Excavation              | foundInExcavation                | EncounterEvent                | Excavation                        |     |
| Item found in the GPSCoordinates        | foundInCoordinates               | Item                          | GPSCoordinates                    |     |
| Item found in a Location                | foundInLocation                  | Item                          | Location                          |     |
| Item found in the Coordinates (within the square) | hasCoordinatesInSquare | Item                  | Coordinates                       |     |
| Elongation Index of the Item            | elongationIndex                  | Item                          | xsd:anyURI                        | [MegaLOD-IndexElongation](https://purl.org/megalod/kos/MegaLOD-IndexElongation) |
| Thickness Index of the Item             | thicknessIndex                   | Item                          | xsd:anyURI                        | [MegaLOD-IndexThickness](https://purl.org/megalod/kos/MegaLOD-IndexThickness) |
| Before or After Christ                  | bcad                             | Instant                       | xsd:anyURI                        | [MegaLOD-BCAD](https://purl.org/megalod/kos/MegaLOD-BCAD) |

# Arrowhead Metadata Scheme

**Namespace:** `https://purl.org/megalod/ms/ah/` (prefix `ah:`)  
**Imports excavation Item:** `excav:` → `https://purl.org/megalod/ms/excavation/`

## Classes

| Label                                   | Vocabulary Term         | Note |
|-----------------------------------------|-------------------------|------|
| Arrowhead                               | Arrowhead               | rdfs:subClassOf excav:Item |
| Morphology                              | Morphology              |      |
| Chipping                                | Chipping                |      |

## Terms

| Label                                                | Vocabulary Term             | Domain     | Range            | VES                    |
|------------------------------------------------------|-----------------------------|------------|------------------|------------------------|
| Shape                                                | shape                       | Arrowhead  | xsd:anyURI       | ah-shape               |
| Variant                                              | variant                     | Arrowhead  | xsd:anyURI       | ah-variant             |
| Point (Sharp=True;Fractured=False)                   | point                       | Morphology | xsd:boolean      |                        |
| Body (Symmetrical=True; Non-symmetrical=False)       | body                        | Morphology | xsd:boolean      |                        |
| Base                                                 | base                        | Morphology | xsd:anyURI       | ah-base                |
| Chipping-mode                                        | chippingMode                | Chipping   | xsd:anyURI       | ah-chippingMode        |
| Chipping-amplitude (Marginal=True;Deep=False)        | chippingAmplitude           | Chipping   | xsd:boolean      |                        |
| Chipping-direction                                   | chippingDirection           | Chipping   | xsd:anyURI       | ah-chippingDirection   |
| Chipping-orientation (Lateral=True;Transverse=False)   | chippingOrientation         | Chipping   | xsd:boolean      |                        |
| Chipping-delineation                                 | chippingDelineation         | Chipping   | xsd:anyURI       | ah-chippingDelineation |
| Chipping-Shape                                       | chippingShape               | Chipping   | xsd:anyURI       | ah-chippingShape       |
| Chipping-location-Side                               | chippingLocationSide        | Chipping   | xsd:anyURI       | ah-chippingLocation    |
| Chipping-Location-Transversal                        | chippingLocationTransversal   | Chipping   | xsd:anyURI       | ah-chippingLocation    |
| The arrowhead has a Morphology                       | hasMorphology               | Arrowhead  | Morphology       |                        |
| The arrowhead has a Chipping                         | hasChipping                 | Arrowhead  | Chipping         |                        |
| Body length of the Arrowhead                         | hasBodyLength               | Arrowhead  | excav:TypometryValue | rdfs:subPropertyOf crm:E54_Dimension |
| Base length of the Arrowhead                         | hasBaseLength               | Arrowhead  | excav:TypometryValue | rdfs:subPropertyOf crm:E54_Dimension |

# Axe Metadata Scheme

**Namespace:** `https://purl.org/megalod/ms/axe/` (prefix `axe:`)

## Classes

| Label | Vocabulary Term | Note |
|-------|-----------------|------|
| Axe   | Axe             | rdfs:subClassOf excav:Item |

## Terms

| Label                | Vocabulary Term      | Domain | Range      | VES                    |
|----------------------|----------------------|--------|------------|------------------------|
| Morphology           | morphology           | Axe    | xsd:anyURI | axe-morphology         |
| Cross Section        | crossSection         | Axe    | xsd:anyURI | axe-morphology         |
| Longitudinal Section | longitudinalSection  | Axe    | xsd:anyURI | axe-longitudinalSection |
| Polished             | polished             | Axe    | xsd:anyURI | axe-polished           |
| Edge                 | edge                 | Axe    | xsd:anyURI | axe-edge               |
| Butt                 | butt                 | Axe    | xsd:anyURI | axe-butt               |
| Traces of use        | tracesOfUse          | Axe    | xsd:anyURI | axe-polished           |
| Traces of reuse      | tracesOfReuse        | Axe    | xsd:anyURI | axe-tracesOfReuse      |
| Traces of fixation   | tracesOfFixation     | Axe    | xsd:boolean |                        |
| Repolishing after fracture | repolishingAfterFracture | Axe | xsd:boolean |                    |

# Loom Weight Metadata Scheme

**Namespace:** `https://purl.org/megalod/ms/loomWeight/` (prefix to be confirmed when `loomWeight.ttl` is published)

## Classes

| Label       | Vocabulary Term | Note |
|-------------|-----------------|------|
| Loom Weight | (TBD)           | rdfs:subClassOf excav:Item |

## Terms

| Label       | Vocabulary Term | Domain | Range | VES |
|-------------|-----------------|--------|-------|-----|
| To be defined | —             | —      | —     | —   |
