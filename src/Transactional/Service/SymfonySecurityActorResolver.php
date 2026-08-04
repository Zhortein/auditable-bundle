<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Service;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Zhortein\AuditableBundle\Transactional\Contract\AuditActorResolverInterface;
use Zhortein\AuditableBundle\Transactional\Exception\ActorResolutionException;
use Zhortein\AuditableBundle\Transactional\Model\AuditActor;

final readonly class SymfonySecurityActorResolver implements AuditActorResolverInterface
{
    private const ACTOR_TYPE = 'authenticated_user';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolveActor(): ?AuditActor
    {
        try {
            $token = $this->tokenStorage->getToken();
        } catch (\Throwable $exception) {
            throw new ActorResolutionException('The current security token could not be read.', previous: $exception);
        }

        if (null === $token) {
            return null;
        }

        try {
            $user = $token->getUser();
        } catch (\Throwable $exception) {
            throw new ActorResolutionException('The current user could not be read from the security token.', previous: $exception);
        }

        if (!$user instanceof UserInterface) {
            if ($token instanceof SwitchUserToken) {
                throw new ActorResolutionException('The current user in the impersonation token is inconsistent.');
            }

            return null;
        }

        $identifier = $this->resolveIdentifier($user, false);
        $impersonatorIdentifier = null;

        if ($token instanceof SwitchUserToken) {
            try {
                $originalUser = $token->getOriginalToken()->getUser();
            } catch (\Throwable $exception) {
                throw new ActorResolutionException('The original user in the impersonation token could not be read.', previous: $exception);
            }
            if (!$originalUser instanceof UserInterface) {
                throw new ActorResolutionException('The original user in the impersonation token is inconsistent.');
            }
            $impersonatorIdentifier = $this->resolveIdentifier($originalUser, true);
        }

        try {
            return new AuditActor(
                type: self::ACTOR_TYPE,
                identifier: $identifier,
                impersonatorIdentifier: $impersonatorIdentifier,
                metadata: [],
            );
        } catch (\Throwable $exception) {
            throw new ActorResolutionException('The resolved security user cannot produce a valid audit actor.', previous: $exception);
        }
    }

    private function resolveIdentifier(UserInterface $user, bool $impersonator): string
    {
        try {
            $identifier = $user->getUserIdentifier();
        } catch (\Throwable $exception) {
            throw new ActorResolutionException($impersonator ? 'The impersonator identifier could not be read.' : 'The current user identifier could not be read.', previous: $exception);
        }

        if (!mb_check_encoding($identifier, 'UTF-8') || 1 === preg_match('/^\s*$/u', $identifier)) {
            throw new ActorResolutionException($impersonator ? 'The impersonator identifier is invalid.' : 'The current user identifier is invalid.');
        }

        return $identifier;
    }
}
