<?php

declare(strict_types=1);

namespace Zhortein\AuditableBundle\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Zhortein\AuditableBundle\Doctrine\AuditableDoctrineListener;
use Zhortein\AuditableBundle\Enum\AuditAction;
use Zhortein\AuditableBundle\Enum\AuditLevel;
use Zhortein\AuditableBundle\Metadata\AuditableMetadataProvider;
use Zhortein\AuditableBundle\Service\ActorResolverInterface;
use Zhortein\AuditableBundle\Service\ChangeDetector;
use Zhortein\AuditableBundle\Service\Historizer;
use Zhortein\AuditableBundle\Tests\Fixtures\App\DoctrineTestFactory;
use Zhortein\AuditableBundle\Tests\Fixtures\Entity\AuditableEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Entity\NonAuditableEntity;
use Zhortein\AuditableBundle\Tests\Fixtures\Service\CollectingAuditEntryWriter;

final class ListenerBehaviorTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CollectingAuditEntryWriter $writer;

    protected function setUp(): void
    {
        $this->entityManager = DoctrineTestFactory::createEntityManager();
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        (new SchemaTool($this->entityManager))->dropSchema($metadata);
        (new SchemaTool($this->entityManager))->createSchema($metadata);
        $this->writer = new CollectingAuditEntryWriter();
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->close();
    }

    public function testCreateUpdateDeleteAndCurrentMessages(): void
    {
        $this->registerListener();
        $entity = new AuditableEntity('before');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        $create = $this->writer->messages[0];
        self::assertSame(AuditAction::CREATE->value, $create->action);
        self::assertSame(AuditLevel::INFO->value, $create->level);
        self::assertSame('Create [Characterized entity]', $create->title);
        self::assertSame('Entity created: '.AuditableEntity::class, $create->description);
        self::assertSame('fixture-context', $create->context);
        self::assertFalse($create->isAuto);

        $entity->setName('after');
        $entity->setSecret('changed-secret');
        $entity->setGloballyIgnored('changed-global');
        $this->entityManager->flush();

        $update = $this->writer->messages[1];
        self::assertSame(AuditAction::UPDATE->value, $update->action);
        self::assertSame(AuditLevel::INFO->value, $update->level);
        self::assertSame('Update [Characterized entity] - 1 field(s) changed', $update->title);
        self::assertSame('Public name : before → after', $update->description);
        self::assertSame(['Public name' => 'Public name : before → after'], $update->data);
        self::assertSame('fixture-context', $update->context);
        self::assertFalse($update->isAuto);

        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        $delete = $this->writer->messages[2];
        self::assertSame(AuditAction::DELETE->value, $delete->action);
        self::assertSame('Delete [Characterized entity]', $delete->title);
        self::assertSame('Entity removed: '.AuditableEntity::class, $delete->description);
        self::assertFalse($delete->isAuto);
        self::assertCount(3, $this->writer->messages);
    }

    public function testNonAuditableEntityProducesNothing(): void
    {
        $this->registerListener();
        $entity = new NonAuditableEntity('before');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $entity->setName('after');
        $this->entityManager->flush();
        $this->entityManager->remove($entity);
        $this->entityManager->flush();

        self::assertSame([], $this->writer->messages);
    }

    public function testUpdateWithOnlyIgnoredChangesProducesNothing(): void
    {
        $this->registerListener();
        $entity = new AuditableEntity('same');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->writer->messages = [];
        $entity->setSecret('changed-secret');
        $entity->setGloballyIgnored('changed-global');
        $this->entityManager->flush();

        self::assertSame([], $this->writer->messages);
    }

    public function testTrackInsertCanBeDisabled(): void
    {
        $this->registerListener(trackInsert: false);
        $this->entityManager->persist(new AuditableEntity('name'));
        $this->entityManager->flush();
        self::assertSame([], $this->writer->messages);
    }

    public function testTrackUpdateCanBeDisabled(): void
    {
        $this->registerListener(trackUpdate: false);
        $entity = new AuditableEntity('before');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->writer->messages = [];
        $entity->setName('after');
        $this->entityManager->flush();
        self::assertSame([], $this->writer->messages);
    }

    public function testTrackDeleteCanBeDisabled(): void
    {
        $this->registerListener(trackDelete: false);
        $entity = new AuditableEntity('name');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $this->writer->messages = [];
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        self::assertSame([], $this->writer->messages);
    }

    public function testGlobalDisableProducesNothing(): void
    {
        $this->registerListener(enabled: false);
        $entity = new AuditableEntity('before');
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        $entity->setName('after');
        $this->entityManager->flush();
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        self::assertSame([], $this->writer->messages);
    }

    private function registerListener(
        bool $enabled = true,
        bool $trackInsert = true,
        bool $trackUpdate = true,
        bool $trackDelete = true,
    ): void {
        $resolver = new class implements ActorResolverInterface {
            public function resolveActorId(): ?string
            {
                return 'fixture-actor';
            }

            public function resolveImpersonatorId(): ?string
            {
                return null;
            }
        };
        $listener = new AuditableDoctrineListener(
            new Historizer($this->writer, $resolver, new NullLogger()),
            new AuditableMetadataProvider(),
            new ChangeDetector(),
            $enabled,
            $trackInsert,
            $trackUpdate,
            $trackDelete,
            ['globallyIgnored'],
        );
        $this->entityManager->getEventManager()->addEventListener(
            [Events::onFlush, Events::postPersist, Events::preRemove],
            $listener,
        );
    }
}
