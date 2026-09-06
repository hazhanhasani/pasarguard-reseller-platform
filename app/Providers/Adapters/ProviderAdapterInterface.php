<?php
namespace App\Providers\Adapters;
interface ProviderAdapterInterface {
    public function readUser(string $username): array;
    public function createUser(string $username, array $groupIds, int $dataLimit, int $expire): array;
    public function modifyUser(string $username, array $changes): array;
    public function setDisabled(string $username, bool $disabled): array;
    public function deleteUser(string $username): void;
    public function resetUsage(string $username): array;
    public function configurations(int $userId): array;
    public function listUsers(int $offset=0,int $limit=25): array;
}
