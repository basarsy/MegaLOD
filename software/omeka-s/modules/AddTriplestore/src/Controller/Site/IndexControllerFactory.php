<?php

namespace AddTriplestore\Controller\Site;

use AddTriplestore\Service\Encounter\EncounterEventService;
use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\CollectingFormToArrowheadMapper;
use AddTriplestore\Service\Ingestion\CollectingFormToExcavationDataMapper;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ingestion\OmekaRestSubmissionService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaSiteResourceService;
use AddTriplestore\Service\SiteMetadataOptionsService;
use AddTriplestore\Service\SiteUserAuthSupport;
use AddTriplestore\Service\Ttl\ArrowheadCanonicalTtlExporter;
use AddTriplestore\Service\Ttl\ArrowheadTtlBuilder;
use AddTriplestore\Service\Ttl\ExcavationTtlBuilder;
use AddTriplestore\Service\Ttl\TtlContentInspectionService;
use AddTriplestore\Service\Ttl\TtlPresentationService;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use AddTriplestore\Service\Ttl\UploadedFileToTtlConverter;
use AddTriplestore\Service\Ttl\XmlToTtlPipeline;
use AddTriplestore\Service\Site\ArrowheadItemClassifier;
use AddTriplestore\Service\Site\ResourceDetailPresentationService;
use AddTriplestore\Service\Site\SiteResourceSearchService;
use AddTriplestore\Service\Site\UserContributedResourcesService;
use AddTriplestore\Service\Upload\TtlUploadOrchestrationService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\Http\Client;
use Laminas\Router\RouteStackInterface;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $httpClient = new Client();
        $router = $container->get(RouteStackInterface::class);
        $megalodConfig = $container->get(MegalodConfig::class);
        $graphDbHttp = $container->get(GraphDbHttpService::class);
        $ttlUriHelper = $container->get(TtlUriHelper::class);
        $excavationTtlBuilder = $container->get(ExcavationTtlBuilder::class);
        $arrowheadTtlBuilder = $container->get(ArrowheadTtlBuilder::class);
        $ttlUriNormalizer = $container->get(TtlUriNormalizer::class);
        $xmlToTtlPipeline = $container->get(XmlToTtlPipeline::class);
        $omekaResourceLookup = $container->get(OmekaResourceLookupService::class);
        $omekaIngestion = $container->get(OmekaIngestionService::class);
        $omekaRestSubmission = $container->get(OmekaRestSubmissionService::class);
        $excavationContext = $container->get(ExcavationItemSetContextService::class);
        $collectingToArrowheadMapper = $container->get(CollectingFormToArrowheadMapper::class);
        $ttlContentInspection = $container->get(TtlContentInspectionService::class);
        $uploadedFileToTtlConverter = $container->get(UploadedFileToTtlConverter::class);
        $encounterEventService = $container->get(EncounterEventService::class);
        $siteMetadataOptions = $container->get(SiteMetadataOptionsService::class);
        $ttlPresentationService = $container->get(TtlPresentationService::class);
        $arrowheadCanonicalTtlExporter = $container->get(ArrowheadCanonicalTtlExporter::class);
        $collectingToExcavationMapper = $container->get(CollectingFormToExcavationDataMapper::class);
        $omekaSiteResource = $container->get(OmekaSiteResourceService::class);
        $siteUserAuthSupport = $container->get(SiteUserAuthSupport::class);
        $ttlUploadOrchestrationService = $container->get(TtlUploadOrchestrationService::class);
        $arrowheadItemClassifier = $container->get(ArrowheadItemClassifier::class);
        $userContributedResourcesService = $container->get(UserContributedResourcesService::class);
        $resourceDetailPresentationService = $container->get(ResourceDetailPresentationService::class);
        $siteResourceSearchService = $container->get(SiteResourceSearchService::class);

        return new IndexController(
            $router,
            $httpClient,
            $megalodConfig,
            $graphDbHttp,
            $ttlUriHelper,
            $excavationTtlBuilder,
            $arrowheadTtlBuilder,
            $ttlUriNormalizer,
            $xmlToTtlPipeline,
            $omekaResourceLookup,
            $omekaIngestion,
            $omekaRestSubmission,
            $excavationContext,
            $collectingToArrowheadMapper,
            $ttlContentInspection,
            $uploadedFileToTtlConverter,
            $encounterEventService,
            $siteMetadataOptions,
            $ttlPresentationService,
            $arrowheadCanonicalTtlExporter,
            $collectingToExcavationMapper,
            $omekaSiteResource,
            $siteUserAuthSupport,
            $ttlUploadOrchestrationService,
            $arrowheadItemClassifier,
            $userContributedResourcesService,
            $resourceDetailPresentationService,
            $siteResourceSearchService
        );
    }
}
