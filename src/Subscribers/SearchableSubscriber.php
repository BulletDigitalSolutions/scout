<?php

namespace LaravelDoctrine\Scout\Subscribers;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Illuminate\Support\Collection;
use Laravel\Scout\EngineManager;
use LaravelDoctrine\Scout\Searchable;
use LaravelDoctrine\Scout\SearchableRepository;

class SearchableSubscriber implements EventSubscriber
{
    /**
     * @var array
     */
    private $indexable = [];

    /**
     * @var array
     */
    private $deleteable = [];

    /**
     * @var EngineManager
     */
    private $engine;

    /**
     * @param EngineManager $engine
     */
    public function __construct(EngineManager $engine)
    {
        $this->engine = $engine;
    }

    /**
     * @return array
     */
    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
            Events::postFlush
        ];
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        foreach ($this->indexable as $event) {
            $this->indexEntity($args->getEntityManager(), $event);
        }

        $this->indexable = [];

        foreach ($this->deleteable as $event) {
            $this->removeEntity($args->getEntityManager(), $event);
        }

        $this->deleteable = [];
    }

    /**
     * @param \Doctrine\ORM\Event\PostPersistEventArgs $event
     */
    public function postPersist(\Doctrine\ORM\Event\PostPersistEventArgs $event)
    {
        $this->scheduleIndexing($event);
    }

    /**
     * @param \Doctrine\ORM\Event\PostUpdateEventArgs $event
     */
    public function postUpdate(\Doctrine\ORM\Event\PostUpdateEventArgs $event)
    {
        $this->scheduleIndexing($event);
    }

    /**
     * @param \Doctrine\ORM\Event\PreRemoveEventArgs $event
     */
    public function preRemove(\Doctrine\ORM\Event\PreRemoveEventArgs $event)
    {
        $object = $event->getObject();

        if ($object instanceof Searchable) {
            $this->deleteable[] = clone $object;
        }
    }

    /**
     * @param \Doctrine\ORM\Event\PostPersistEventArgs $event
     */
    public function scheduleIndexing(\Doctrine\ORM\Event\PostPersistEventArgs $event)
    {
        $object = $event->getObject();

        if ($object instanceof Searchable) {
            $this->indexable[] = $object;
        }
    }

    /**
     * @param EntityManagerInterface $em
     * @param Searchable             $object
     */
    private function indexEntity(EntityManagerInterface $em, Searchable $object)
    {
        $repository = $this->getRepository($em, $object);

        $repository->makeEntitiesSearchable(new Collection([$object]));
    }

    /**
     * @param EntityManagerInterface $em
     * @param Searchable             $object
     */
    private function removeEntity(EntityManagerInterface $em, Searchable $object)
    {
        $repository = $this->getRepository($em, $object);

        $repository->removeSearchableEntities(new Collection([$object]));
    }

    /**
     * @param  EntityManagerInterface $em
     * @param  Searchable             $object
     * @return SearchableRepository
     */
    private function getRepository(EntityManagerInterface $em, Searchable $object)
    {
        $class      = get_class($object);
        $cmd        = $em->getClassMetadata($class);
        $repository = $em->getRepository($class);

        if (!$repository instanceof SearchableRepository) {
            $repository = new SearchableRepository(
                $em,
                $cmd,
                $this->engine
            );
        }

        $object->setClassMetaData($cmd);
        $object->setSearchableAs($repository->searchableAs());

        return $repository;
    }
}
