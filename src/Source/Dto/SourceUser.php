<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Source\Dto;

use Carbon\CarbonImmutable;

final readonly class SourceUser
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public int $id,
        public string $login,
        public string $email,
        public string $displayName,
        public string $nicename,
        public ?string $url,
        public string $passwordHash,
        public ?CarbonImmutable $registeredAt,
        public array $roles,
        public bool $rolesMalformed,
        public MetaBag $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'login' => $this->login,
            'email' => $this->email,
            'display_name' => $this->displayName,
            'nicename' => $this->nicename,
            'url' => $this->url,
            'password_hash' => $this->passwordHash,
            'registered_at' => $this->registeredAt?->toIso8601String(),
            'roles' => $this->roles,
            'meta' => $this->meta->toArray(),
        ];
    }
}
