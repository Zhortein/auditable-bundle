<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityActorResolver implements ActorResolverInterface
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function resolveActorId(): ?string
    {
        return $this->extractId($this->security->getUser());
    }

    public function resolveImpersonatorId(): ?string
    {
        $token = $this->security->getToken();
        if (!$token instanceof SwitchUserToken) {
            return null;
        }

        return $this->extractId($token->getOriginalToken()->getUser());
    }

    private function extractId(mixed $user): ?string
    {
        if (!$user instanceof UserInterface) {
            return null;
        }

        if (method_exists($user, 'getId')) {
            $id = $user->getId();

            return null !== $id ? (string) $id : null;
        }

        return $user->getUserIdentifier();
    }
}
