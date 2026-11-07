<?php

declare(strict_types=1);

namespace ItsJustVita\WpMigrator\Mapping;

use Illuminate\Config\Repository;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use ItsJustVita\WpMigrator\Import\ImportContext;
use ItsJustVita\WpMigrator\Import\SourceType;
use ItsJustVita\WpMigrator\Source\Dto\SourceUser;
use ItsJustVita\WpMigrator\Support\WpDates;

/**
 * Writes users into the application's user model.
 *
 * The password hash is written as it is, bypassing a "hashed" cast, so the
 * hasher can verify it later. A user whose email address already exists in
 * the application is linked to that record and never changed by the import.
 */
class DefaultUserMapper implements UserMapper
{
    public const array DEFAULT_ATTRIBUTES = [
        'name' => 'display_name',
        'email' => 'email',
        'password' => 'password_hash',
        'created_at' => 'registered_at',
    ];

    public function __construct(protected readonly Repository $config) {}

    public function map(SourceUser $user, ImportContext $context): ?Model
    {
        $class = $this->modelClass();
        $existing = $context->existing(SourceType::User, $user->id, $class);

        if ($existing !== null && $context->wasLinked(SourceType::User, $user->id)) {
            $context->markLinked();

            return $existing;
        }

        $email = $this->email($user, $context);

        if ($existing === null && ($match = $this->findByEmail($class, $email)) !== null) {
            $context->markLinked();
            $context->warn($user->id, sprintf(
                'Linked to existing user #%s with the same email address; that user was left unchanged.',
                $match->getKey(),
            ));

            return $match;
        }

        $model = $existing ?? new $class;
        $raw = [];

        foreach ($this->attributes() as $attribute => $source) {
            $value = $this->value($user, $source, $email);

            if ($source === 'password_hash') {
                $raw[$attribute] = $value;

                continue;
            }

            $model->setAttribute($attribute, $value);
        }

        $roleAttribute = $this->config->get('wp-migrator.users.role_attribute');

        if (is_string($roleAttribute) && $roleAttribute !== '') {
            $model->setAttribute($roleAttribute, $this->role($user));
        }

        if ($raw !== []) {
            $model->setRawAttributes(array_merge($model->getAttributes(), $raw));
        }

        $model->save();

        return $model;
    }

    /**
     * @return class-string<Model>
     */
    protected function modelClass(): string
    {
        $class = $this->config->get('wp-migrator.users.model') ?? $this->config->get('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException('wp-migrator.users.model must name an Eloquent model class.');
        }

        return $class;
    }

    /**
     * Target attribute => source field. Source fields: login, email,
     * display_name, nicename, url, password_hash, registered_at, meta:<key>.
     *
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        $attributes = $this->config->get('wp-migrator.users.attributes', self::DEFAULT_ATTRIBUTES);

        return is_array($attributes) ? array_map(strval(...), $attributes) : self::DEFAULT_ATTRIBUTES;
    }

    protected function value(SourceUser $user, string $source, string $email): mixed
    {
        return match (true) {
            $source === 'login' => $user->login,
            $source === 'email' => $email,
            $source === 'display_name' => $user->displayName !== '' ? $user->displayName : $user->login,
            $source === 'nicename' => $user->nicename,
            $source === 'url' => $user->url,
            $source === 'password_hash' => $user->passwordHash,
            $source === 'registered_at' => WpDates::forStorage($user->registeredAt),
            str_starts_with($source, 'meta:') => $user->meta->get(substr($source, 5)),
            default => throw new InvalidArgumentException(sprintf('Unknown user source field "%s".', $source)),
        };
    }

    protected function role(SourceUser $user): ?string
    {
        $map = $this->config->get('wp-migrator.users.roles', []);
        $map = is_array($map) ? $map : [];

        foreach ($user->roles as $role) {
            $mapped = $map[$role] ?? $role;

            return is_scalar($mapped) ? (string) $mapped : $role;
        }

        return null;
    }

    protected function email(SourceUser $user, ImportContext $context): string
    {
        if ($user->email !== '') {
            return $user->email;
        }

        $placeholder = sprintf('wp-user-%d@invalid.localhost', $user->id);
        $context->warn($user->id, sprintf('User "%s" has no email address; the placeholder %s was used.', $user->login, $placeholder));

        return $placeholder;
    }

    /**
     * @param  class-string<Model>  $class
     */
    protected function findByEmail(string $class, string $email): ?Model
    {
        if (! $this->config->boolean('wp-migrator.users.link_existing_by_email', true)) {
            return null;
        }

        $attribute = array_search('email', $this->attributes(), true);

        if (! is_string($attribute)) {
            return null;
        }

        return $class::query()->where($attribute, $email)->first();
    }
}
