<?php

namespace AddTriplestore\Controller\Site;

use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\Ingestion\OmekaIngestionService;
use AddTriplestore\Service\Ingestion\OmekaResourceLookupService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaApiCredentialService;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
use AddTriplestore\Service\Ttl\XmlToTtlPipeline;
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
        $omekaApiCredentials = $container->get(OmekaApiCredentialService::class);
        $ttlUriHelper = $container->get(TtlUriHelper::class);
        $ttlUriNormalizer = $container->get(TtlUriNormalizer::class);
        $xmlToTtlPipeline = $container->get(XmlToTtlPipeline::class);
        $omekaResourceLookup = $container->get(OmekaResourceLookupService::class);
        $omekaIngestion = $container->get(OmekaIngestionService::class);

        return new IndexController(
            $router,
            $httpClient,
            $megalodConfig,
            $graphDbHttp,
            $omekaApiCredentials,
            $ttlUriHelper,
            $ttlUriNormalizer,
            $xmlToTtlPipeline,
            $omekaResourceLookup,
            $omekaIngestion
        );
    }
}
