<?php

declare(strict_types=1);

namespace AddTriplestore\Service\Upload;

use AddTriplestore\Service\Encounter\EncounterEventService;
use AddTriplestore\Service\ExcavationItemSetContextService;
use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\Ingestion\OmekaRestSubmissionService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaSiteResourceService;
use AddTriplestore\Service\Ttl\TtlContentInspectionService;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

final class TtlUploadOrchestrationServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        return new TtlUploadOrchestrationService(
            $container->get(MegalodConfig::class),
            $container->get(GraphDbHttpService::class),
            $container->get(TtlUriNormalizer::class),
            $container->get(EncounterEventService::class),
            $container->get(TtlContentInspectionService::class),
            $container->get(OmekaResourceLookupService::class),
            $container->get(ExcavationItemSetContextService::class),
            $container->get(OmekaIngestionService::class),
            $container->get(OmekaRestSubmissionService::class),
            $container->get(OmekaSiteResourceService::class)
        );
    }
}
