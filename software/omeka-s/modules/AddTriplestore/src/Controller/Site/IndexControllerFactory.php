<?php

namespace AddTriplestore\Controller\Site;

use AddTriplestore\Service\GraphDbHttpService;
use AddTriplestore\Service\MegalodConfig;
use AddTriplestore\Service\OmekaApiCredentialService;
use AddTriplestore\Service\Ttl\TtlUriHelper;
use AddTriplestore\Service\Ttl\TtlUriNormalizer;
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

        return new IndexController(
            $router,
            $httpClient,
            $megalodConfig,
            $graphDbHttp,
            $omekaApiCredentials,
            $ttlUriHelper,
            $ttlUriNormalizer
        );
    }
}
