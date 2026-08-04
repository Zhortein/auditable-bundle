<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Fixtures\Transactional\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class TestUser implements UserInterface
{
    /** @param non-empty-string $identifier */
    public function __construct(private string $identifier)
    {
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->identifier;
    }

    public function getId(): never
    {
        throw new \LogicException('getId() must not be called.');
    }

    public function id(): never
    {
        throw new \LogicException('id() must not be called.');
    }
}
