# MegaLOD Project Metadata Schemes
These four metadata schemes were created to be used in the context of the MegaLOD Metadata Application Profile (see https://purl.org/megalod/map)

# Excavation Metadata Scheme
URI: https//purl.org/megalod/ms/excav/

## Classes

| Label                                   | Vocabulary Term         | Note                                                             |
|-----------------------------------------|-------------------------|------------------------------------------------------------------|
| Excavation                              | Excavation              | rdfs:subClassOf crmarchaeo:A9_Archaeological_Excavation          |                                 
| Archaeologist                           | Archaeologist           | rdfs:subClassOf foaf:Person; owl:equivalentClass crm:E21_Person; |                               
| Location                                | Location                | rdfs:subClassOf dbo:Place                                        |                           
| GPS Coordinates                         | GPSCoordinates          | rdfs:subClassOf geo:SpatialThing                                 |                     
| Encounter Event                         | EncounterEvent          | rdfs:subClassOf crmsci:S19_Encounter_Event                       |                                   
| Stratigraphic Volume Unit               | StratigraphicVolumeUnit | rdfs:subClassOf crmarchaeo:A2_Stratigraphic_Volume_Unit          |                                       
| Context                                 | Context                 | rdfs:subClassOf crmarchaeo:A1_Excavation_Processing_Unit         |
| TimeLine                                | TimeLine                | rdfs:subClassOf time:TemporalEntity|
| Instant                                 | Instant                 | rdfs:subClassOf time:Instant|
| Square                                  | Square                  | rddfs:subClassOf schema:Place |
| Coordinates                             | Coordinates             | rddfs:subClassOf schema:GeoCoordinates |
| Weight                                  | Weight                  | rddfs:subClassOf schema:QuantitativeValue |
| Depth                                   | Depth                   | rddfs:subClassOf schema:QuantitativeValue |
| TypometryValue                          | TypometryValue          | rddfs:subClassOf schema:QuantitativeValue |
| Item                                    | Item                    | rdfs:subClassOf crm:E24_Physical_Man-Made_Thing|   



## Terms
| Label                                   | Vocabulary Term                  | Domain                              |Range                                        | VES|
|-----------------------------------------|----------------------------------|-------------------------------------|---------------------------------------------|----|
| Has GPS Coordinates                     | hasGPSCoordinates                | Location                      | GPSCoordinates                        | |
| Has person in charge                    | hasPersonInCharge                | Excavation                    | Archaeologist                         | |
| Has context                             | hasContext                       | Excavation                    | Context                               | |
| has Stratigraphic Unit                  | hasSVU                           | Context                       | StratigraphicVolumeUnit               | |
| Has Square                              | hasSquare                        | Square                        | Excavation                            | |
| hasTimeLine                             | hasTimeLine                      | StratigraphicVolumeUnit       | TimeLine                              | |                 
| Item found in a StratigraphicVolumeUnit | foundInSVU                       | EncounterEvent                | StratigraphicVolumeUnit               | |
| Item found in a Context                 | foundInContext                   | EncounterEvent                | Context                               | |
| Item found in a Excavation              | foundInExcavation                | EncounterEvent                | Excavation                            | |
| Item found in the GPSCoordinates        | foundInCoordinates               | Item                          | GPSCoordinates                        |   |          
| Item found in a Location                | foundInLocation                  | Item                          | Location                              | |
| Item found in the Coordinates (within the square) | hasCoordinatesInSquare | Item                          | Coordinates                           |   |        
| Elongation Index of the Item            | elongationIndex                  | Item                          | xsd:anyURI                            |  [MegaLOD-indexElongation](http://purl.org/megalod/kos/MegaLod-indexElomngation)|
| Thickness  Index of the Item            | thicknessIndex                   | Item                          | xsd:anyURI                            |  [MegaLOD-indexThickness](http://purl.org/megalod/kos/MegaLod-indexThickness)|
| Before or After Christ                  | bcad                             | Instant                       | xsd:anyURI                            | [MegaLOD-BCAD](http://purl.org/megalod/kos/MegaLOD-BCAD) |


     
# Arrowhead Metadata Scheme
URI: https//purl.org/megalod/ms/ah/

namespaces--> excav:https//purl.org/megalod/ms/excav/

## Classes
| Label                                   | Vocabulary Term         | Note |
|-----------------------------------------|-------------------------|------|
| Arrowhead                               | Arrowhead               | rdfs:subClassOf excav:Item |
| Morphology                              | Morphology              |     |   
| Chipping                                | Chipping                |     |  


## Terms
| Label                                                | Vocabulary Term             | Domain                        | Range            | VES                    | Notes |
|------------------------------------------------------|-----------------------------|-------------------------------|------------------|------------------------|------|
| Shape                                                | shape                       | Arrowhead                     | xsd:anyURI       | ah-shape               | |
| Variant                                              | variant                     | Arrowhead                     | xsd:anyURI       | ah-variant             | |
| Point (Sharp=True;Fractured=False)                   | point                       | Morphology                    | xsd:boolean      |                        | |
| Body (Symmetrical=True; Non-symmetrical=False)       | body                        | Morphology                    | xsd:boolean      |                        | |
| Base                                                 | base                        | Morphology                    | xsd:anyURI       | ah-base                | |
| Chipping-mode                                        | chippingMode                        | Chipping                      | xsd:anyURI       | ah-chippingMode        | |
| Chipping-amplitude (Marginal=True;Deep=False)        | chippingAmplitude                   | Chipping                      | xsd:boolean      |                        | |
| Chipping-direction                                   | chippingDirection                   | Chipping                      | xsd:anyURI       | ah-chippingDirection   | |
| Chipping-orientation (Lateral=True;Transverse=False) | chippingOrientation                 | Chipping                      | xsd:boolean      |                       | |
| Chipping-delineation                                 | chippingDileneation                 | Chipping                      | xsd:anyURI       | ah-chippingDelineation | |
| Chipping-Shape                                       | chippingShape               | Chipping                      | xsd:anyURI       | ah-chippingShape       | |
| Chipping-location-Side                               | chippingLocationSide       | Chipping                      | xsd:anyURI       | ah-chippingLocation    | |
| Chipping-Location-Transversal                        | chippingLocationTransversal | Chipping                      | xsd:anyURI       | ah-chippingLocation    | |
| The arrowhead has a Morphology                       | hasMorphology               | Arrowhead                     | Morphology       |                        | |
| The arrowhead has a Chipping                         | hasChipping                 | Arrowhead                     | Chipping         | |  |
| Body length of the Arrowhead                         | bodyLength                  | Arrowhead                     | excav:TypometryValue | | rdfs:subPropertyOf crm:E54_Dimension |
| Base length of the Arrowhead                         | baseLength                  | Arrowhead                     | excav:TypometryValue | | rdfs:subPropertyOf crm:E54_Dimension |




# Axe Metadata Scheme
URI: https//purl.org/megalod/ms/axe/

## Classes
| Label                                                | Vocabulary Term                |  Note |
|------------------------------------------------------|--------------------------------|----------------------------------|
|Axe                                                   | ax:axe                          |   rdfs:subClassOf excav:Item                               |             


## Terms
| Label                                                | Vocabulary Term                | Domain                           | Range            | VES                       |
|------------------------------------------------------|--------------------------------|----------------------------------|------------------|---------------------------|
| Morphology                                           | morphology                     | Axe                              | xsd:anyURI       |  axe-morphology           |
| Cross Section                                        | crossSection                   | Axe                              | xsd:anyURI       |  axe-morphology           |
| Longitudinal Section                                 | longitudinalSection            | Axe                              | xsd:anyURI       |  axe-longitudinalSection  |
| Polished                                             | polished                       | Axe                              | xsd:anyURI       |  axe-polished             |
| Edge                                                 | edge                           | Axe                              | xsd:anyURI       |  axe-edge                 |
| Butt                                                 | butt                           | Axe                              | xsd:anyURI       |  axe-butt                 |
| Traces of use                                        | tracesOfUse                    | Axe                              | xsd:anyURI       |  axe-polished             |
| Traces of reuse                                      | tracesOfReuse                  | Axe                              | xsd:anyURI       |  axe-tracesOfReuse        |
| Traces of fixation                                   | tracesOfFixation               | Axe                              | xsd:boolean      |           |
| Repolishing after fracture                           | repolishingAfterFracture       | Axe                              | xsd:boolean      |           |


# Loom Weight Metadata Scheme
URI: https//purl.org/megalod/ms/loomWeight/

## Classes
| Label                                                | Vocabulary Term                |  Note |
|------------------------------------------------------|--------------------------------|----------------------------------|
| Loom Weight                                          | ax:LoomWeight                  | rdfs:subClassOf excav:Item               |             


## Terms
| Label                                                | Vocabulary Term                | Domain                           | Range            | VES                    |
|------------------------------------------------------|--------------------------------|----------------------------------|------------------|------------------------|
| To be Defined |      |                                  |                  |                        |
