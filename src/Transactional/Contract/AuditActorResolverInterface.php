<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Transactional\Contract;

use Zhortein\AuditableBundle\Transactional\Model\AuditActor;

/**
 * Resolves an actor without implicitly capturing sensitive data.
 *
 * Null represents the absence of a resolved actor. The strict contract does
 * not absorb resolver exceptions.
 */
interface AuditActorResolverInterface
{
    public function resolveActor(): ?AuditActor;
}
