<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Service;

interface ActorResolverInterface
{
    public function resolveActorId(): ?string;

    public function resolveImpersonatorId(): ?string;
}
