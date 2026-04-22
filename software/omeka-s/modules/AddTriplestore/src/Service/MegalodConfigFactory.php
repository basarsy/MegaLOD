<?php

namespace AddTriplestore\Service;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MegalodConfigFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $this->loadMegalodConfig();
        return new MegalodConfig(
            $config['graphdb_base_url'],
            $config['graphdb_repository'],
            $config['megalod_public_base_uri'],
            $config['megalod_local_base_uri'],
            $config['graphdb_workbench_url']
        );
    }

    /**
     * @return array{graphdb_base_url: string, graphdb_repository: string,
     *               megalod_public_base_uri: string, megalod_local_base_uri: string,
     *               graphdb_workbench_url: string}
     */
    private function loadMegalodConfig(): array
    {
        $configFile = dirname(__DIR__, 2) . '/config/graphdb.config.php';
        $fileConfig = [];
        if (file_exists($configFile)) {
            $loaded = include $configFile;
            if (is_array($loaded)) {
                $fileConfig = $loaded;
            }
        }

        $graphdbBaseUrl = $this->resolveValue(
            $fileConfig, 'graphdb_base_url', 'GRAPHDB_BASE_URL'
        );
        if (empty($graphdbBaseUrl)) {
            $host = getenv('GRAPHDB_HOST');
            $port = getenv('GRAPHDB_PORT');
            if ($host && $port) {
                $graphdbBaseUrl = "http://$host:$port";
            }
        }
        if (empty($graphdbBaseUrl)) {
            throw new \RuntimeException(
                'GraphDB connection is not configured. '
                . 'Set GRAPHDB_BASE_URL (or GRAPHDB_HOST + GRAPHDB_PORT) environment variables, '
                . 'or configure graphdb_base_url in modules/AddTriplestore/config/graphdb.config.php.'
            );
        }

        $graphdbRepo = $this->resolveValue(
            $fileConfig, 'graphdb_repository', 'GRAPHDB_REPOSITORY'
        );
        if (empty($graphdbRepo)) {
            throw new \RuntimeException(
                'GraphDB repository is not configured. '
                . 'Set GRAPHDB_REPOSITORY environment variable '
                . 'or configure graphdb_repository in graphdb.config.php.'
            );
        }

        $publicBaseUri = $this->resolveValue(
            $fileConfig, 'megalod_public_base_uri', 'MEGALOD_PUBLIC_BASE_URI'
        ) ?: 'https://purl.org/megalod/';

        $localBaseUri = $this->resolveValue(
            $fileConfig, 'megalod_local_base_uri', 'MEGALOD_LOCAL_BASE_URI'
        );
        if (empty($localBaseUri)) {
            throw new \RuntimeException(
                'MegaLOD local base URI is not configured. '
                . 'Set MEGALOD_LOCAL_BASE_URI environment variable '
                . 'or configure megalod_local_base_uri in graphdb.config.php.'
            );
        }

        $workbenchUrl = $this->resolveValue(
            $fileConfig, 'graphdb_workbench_url', 'GRAPHDB_WORKBENCH_URL'
        ) ?: rtrim($graphdbBaseUrl, '/') . '/';

        return [
            'graphdb_base_url'        => rtrim($graphdbBaseUrl, '/'),
            'graphdb_repository'      => $graphdbRepo,
            'megalod_public_base_uri' => rtrim($publicBaseUri, '/') . '/',
            'megalod_local_base_uri'  => rtrim($localBaseUri, '/') . '/',
            'graphdb_workbench_url'   => $workbenchUrl,
        ];
    }

    private function resolveValue(array $fileConfig, string $key, string $envVar): ?string
    {
        $value = $fileConfig[$key] ?? null;
        if (!empty($value) && $value !== 'CHANGE_ME') {
            return $value;
        }
        $value = getenv($envVar) ?: null;
        if (!empty($value) && $value !== 'CHANGE_ME') {
            return $value;
        }
        return null;
    }
}
