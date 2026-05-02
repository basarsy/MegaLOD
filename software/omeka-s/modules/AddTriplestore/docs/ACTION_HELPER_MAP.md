# IndexController ↔ services (Issue 6 — Phase 0)

**Phase 8 / external PRE_DEPLOYMENT:** Mark Issue 6 closed only after the golden flows below are exercised on staging.

Short map of site actions vs injected services after decomposition. Keeps refactoring from drifting into half-moved helpers.

**Bootstrap / DI hygiene:** [`IndexController.php`](../src/Controller/Site/IndexController.php) must keep `use AddTriplestore\Service\Site\UserContributedResourcesService` (and other `Service\…` imports). Without it, PHP resolves an unqualified `UserContributedResourcesService` type hint under `Controller\Site` and **throws `TypeError` on PHP 8**. `IndexController` currently has **28** constructor parameters (router, HTTP client, `MegalodConfig`, and **25** AddTriplestore / Omeka collaborator services).

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
| `dashboardAction`, `aboutUsAction`, `downloadTemplateAction` | `Omeka\Api` plugin / views |
| `myDataAction` | `UserContributedResourcesService`, `Omeka\Api` (via service) |
| `uploadAction` | `UploadRequestContext` (DTO), `TtlUploadOrchestrationService`, `ExcavationTtlBuilder`, `UploadedFileToTtlConverter`, `CollectingFormToExcavationDataMapper`, `OmekaSiteResourceService`, `excavationItemSetContextService` |
| `processCollectingFormAction` | `CollectingFormToArrowheadMapper`, upload path above |
| `searchAction` | `SiteSearchQuery`, `SiteResourceSearchService`, `SiteMetadataOptionsService` |
| `viewDetailsAction` | `ResourceDetailPresentationService` |
| `downloadTtlAction` | `TtlPresentationService`, `ArrowheadCanonicalTtlExporter`, `ArrowheadItemClassifier` |
| listeners in `Module` | `MegalodConfig`, `GraphDbHttpService` |
