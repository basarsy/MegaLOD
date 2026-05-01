<?php

declare(strict_types=1);

namespace AddTriplestore\Service;

use Doctrine\DBAL\Connection;

/**
 * Site-only signup (guest users) and viewer site_permissions without pulling SQL into controllers.
 */
final class SiteUserAuthSupport
{
    /** @var Connection */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Create a guest user row and associate them with one site as viewer.
     *
     * @param array{email?: string, name?: string, password?: string} $userData
     *
     * @return array{success: bool, error: string|null}
     */
    public function createSiteOnlyUser(array $userData, int $siteId): array
    {
        try {
            $checkSql = 'SELECT id FROM user WHERE email = ?';
            $stmt = $this->connection->prepare($checkSql);
            $stmt->execute([$userData['email'] ?? '']);

            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'A user with this email already exists'];
            }

            $hashedPassword = password_hash((string) ($userData['password'] ?? ''), PASSWORD_DEFAULT);

            $insertSql = 'INSERT INTO user (email, name, role, is_active, password_hash, created) VALUES (?, ?, ?, ?, ?, ?)';
            $stmt = $this->connection->prepare($insertSql);

            $result = $stmt->execute([
                $userData['email'] ?? '',
                $userData['name'] ?? '',
                'guest',
                1,
                $hashedPassword,
                date('Y-m-d H:i:s'),
            ]);

            if ($result) {
                $userId = (int) $this->connection->lastInsertId();
                $this->addUserToSite($userId, $siteId);

                return ['success' => true];
            }

            return ['success' => false, 'error' => 'Failed to create user account'];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'email') !== false || strpos($msg, 'Duplicate') !== false) {
                return ['success' => false, 'error' => 'A user with this email already exists'];
            }

            return ['success' => false, 'error' => 'Failed to create account: ' . $msg];
        }
    }

    private function addUserToSite(int $userId, int $siteId): void
    {
        try {
            $insertSql = 'INSERT INTO site_permission (site_id, user_id, role) VALUES (?, ?, ?)';
            $stmt = $this->connection->prepare($insertSql);
            $stmt->execute([$siteId, $userId, 'viewer']);
        } catch (\Exception $e) {
        }
    }
}
