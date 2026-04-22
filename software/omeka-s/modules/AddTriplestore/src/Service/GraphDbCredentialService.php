<?php

namespace AddTriplestore\Service;

/**
 * Resolves GraphDB credentials from graphdb.config.php or environment.
 */
final class GraphDbCredentialService
{
    private function configFilePath(): string
    {
        return OMEKA_PATH . '/modules/AddTriplestore/config/graphdb.config.php';
    }

    /**
     * @return array{username: string, password: string}
     *
     * @throws \RuntimeException
     */
    public function getWriteCredentials(): array
    {
        $configFile = $this->configFilePath();
        if (file_exists($configFile)) {
            $credentials = include $configFile;
            if (is_array($credentials)
                && !empty($credentials['username']) && !empty($credentials['password'])
                && $credentials['username'] !== 'CHANGE_ME'
                && $credentials['password'] !== 'CHANGE_ME') {
                return [
                    'username' => $credentials['username'],
                    'password' => $credentials['password'],
                ];
            }
        }

        $user = getenv('GRAPHDB_USERNAME');
        $pass = getenv('GRAPHDB_PASSWORD');
        if (!empty($user) && !empty($pass)) {
            return ['username' => $user, 'password' => $pass];
        }

        throw new \RuntimeException(
            'GraphDB credentials are not configured. '
            . 'Set GRAPHDB_USERNAME/GRAPHDB_PASSWORD environment variables or '
            . 'configure modules/AddTriplestore/config/graphdb.config.php.'
        );
    }

    /**
     * Read-only user when configured; otherwise same as write credentials.
     *
     * @return array{username: string, password: string}
     *
     * @throws \RuntimeException
     */
    public function getReadonlyCredentials(): array
    {
        $configFile = $this->configFilePath();
        if (file_exists($configFile)) {
            $credentials = include $configFile;
            if (is_array($credentials)
                && !empty($credentials['readonly_username']) && !empty($credentials['readonly_password'])
                && $credentials['readonly_username'] !== 'CHANGE_ME'
                && $credentials['readonly_password'] !== 'CHANGE_ME') {
                return [
                    'username' => $credentials['readonly_username'],
                    'password' => $credentials['readonly_password'],
                ];
            }
        }

        $user = getenv('GRAPHDB_READONLY_USERNAME');
        $pass = getenv('GRAPHDB_READONLY_PASSWORD');
        if (!empty($user) && !empty($pass)) {
            return ['username' => $user, 'password' => $pass];
        }

        return $this->getWriteCredentials();
    }
}
