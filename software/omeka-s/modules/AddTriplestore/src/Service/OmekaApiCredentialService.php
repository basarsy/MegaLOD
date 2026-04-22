<?php

namespace AddTriplestore\Service;

/**
 * Omeka S REST API key credentials for server-side ingestion.
 */
final class OmekaApiCredentialService
{
    private function configFilePath(): string
    {
        return OMEKA_PATH . '/modules/AddTriplestore/config/graphdb.config.php';
    }

    /**
     * @return array{base_url: string, key_identity: string, key_credential: string}
     *
     * @throws \RuntimeException
     */
    public function getApiCredentials(): array
    {
        $configFile = $this->configFilePath();
        if (file_exists($configFile)) {
            $config = include $configFile;
            if (is_array($config)
                && !empty($config['omeka_key_identity']) && !empty($config['omeka_key_credential'])
                && $config['omeka_key_identity'] !== 'CHANGE_ME'
                && $config['omeka_key_credential'] !== 'CHANGE_ME') {
                $baseUrl = $config['omeka_base_url'] ?? getenv('OMEKA_BASE_URL') ?: null;
                if (empty($baseUrl) || $baseUrl === 'CHANGE_ME') {
                    throw new \RuntimeException(
                        'Omeka S API base URL is not configured. '
                        . 'Set OMEKA_BASE_URL environment variable or configure omeka_base_url in graphdb.config.php.'
                    );
                }
                return [
                    'base_url'       => $baseUrl,
                    'key_identity'   => $config['omeka_key_identity'],
                    'key_credential' => $config['omeka_key_credential'],
                ];
            }
        }

        $identity   = getenv('OMEKA_KEY_IDENTITY');
        $credential = getenv('OMEKA_KEY_CREDENTIAL');
        $baseUrl    = getenv('OMEKA_BASE_URL') ?: null;
        if (!empty($identity) && !empty($credential)) {
            if (empty($baseUrl) || $baseUrl === 'CHANGE_ME') {
                throw new \RuntimeException(
                    'Omeka S API base URL is not configured. '
                    . 'Set OMEKA_BASE_URL environment variable or configure omeka_base_url in graphdb.config.php.'
                );
            }
            return [
                'base_url'       => $baseUrl,
                'key_identity'   => $identity,
                'key_credential' => $credential,
            ];
        }

        throw new \RuntimeException(
            'Omeka S API credentials are not configured. '
            . 'Set OMEKA_KEY_IDENTITY/OMEKA_KEY_CREDENTIAL environment variables or '
            . 'configure modules/AddTriplestore/config/graphdb.config.php.'
        );
    }
}
