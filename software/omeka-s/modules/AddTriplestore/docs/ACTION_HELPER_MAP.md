# IndexController ↔ services (Issue 6 — Phase 0)

**Phase 8 / external PRE_DEPLOYMENT:** Mark Issue 6 closed only after the golden flows below are exercised on staging.

**Phase 8 / external PRE_DEPLOYMENT:** Mark Issue 6 closed only after the golden flows below are exercised on staging.

Short map of site actions vs injected services after decomposition. Keeps refactoring from drifting into half-moved helpers.

## Golden staging flows (manual)

1. TTL file upload into an existing item set (arrowhead continuity).
2. Excavation XML or TTL upload and auto item-set creation where applicable.
3. Collecting-form excavation → Omeka item set → redirect to arrowhead upload.
4. View details / my-data listing (property labels).
5. Search filters (GraphDB-backed option lists).
6. Download TTL: item sets via GraphDB CONSTRUCT + organize; arrowhead items prefer canonical exporter when heuristic matches, else GraphDB path.
7. GraphDB-aligned delete listener (after item removal in Omeka).

| Action | Main collaborators |
|--------|-------------------|
| `indexAction`, `sparqlAction` | `MegalodConfig` (workbench URL) |
| `signupAction` | `SiteUserAuthSupport` |
| `loginAction`, `logoutAction` | (Laminas / Omeka auth; CSRF still on controller) |
| `dashboardAction`, `myDataAction`, `downloadTemplateAction`, `downloadAction` helpers | Existing services + `Omeka\Api` plugin |
| `uploadAction` | `TtlUploadOrchestrationService`, `ExcavationTtlBuilder`, `UploadedFileToTtlConverter`, `CollectingFormToExcavationDataMapper`, `OmekaSiteResourceService`, `excavationItemSetContextService` |
| `processCollectingFormAction` | `CollectingFormToArrowheadMapper`, upload path above |
| `searchAction` | `SiteMetadataOptionsService`, `Omeka\Api` |
| `viewDetailsAction` | `VocabularyLabelService`, plus resource loading |
| `downloadTtlAction` | `TtlPresentationService`, `ArrowheadCanonicalTtlExporter`, `isLikelyArrowheadResource` |
| listeners in `Module` | `MegalodConfig`, `GraphDbHttpService` |
