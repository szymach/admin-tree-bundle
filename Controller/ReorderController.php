<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Bundle\AdminTreeBundle\Controller;

use FSi\Bundle\AdminBundle\Admin\CRUD\DataIndexerElement;
use FSi\Bundle\AdminBundle\Admin\Element as AdminElement;
use FSi\Bundle\AdminBundle\Doctrine\Admin\Element as AdminDoctrineElement;
use FSi\Bundle\AdminBundle\Event\AdminEvent;
use FSi\Bundle\AdminTreeBundle\Event\MovedDownTreeEvent;
use FSi\Bundle\AdminTreeBundle\Event\MovedUpTreeEvent;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface as PsrEventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use function get_class;
use function sprintf;

class ReorderController
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EventDispatcherInterface|PsrEventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(RouterInterface $router, EventDispatcherInterface $eventDispatcher)
    {
        $this->router = $router;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param DataIndexerElement&AdminDoctrineElement $element
     * @param mixed $id
     * @param Request $request
     * @return Response
     */
    public function moveUpAction(DataIndexerElement $element, $id, Request $request): Response
    {
        $entity = $this->getEntity($element, $id);

        $this->getRepository($element)->moveUp($entity);
        $element->getObjectManager()->flush();

        $this->dispatchEvent(new MovedUpTreeEvent($element, $request, $entity));

        return $this->getRedirectResponse($element, $request);
    }

    /**
     * @param DataIndexerElement&AdminDoctrineElement $element
     * @param mixed $id
     * @param Request $request
     * @return Response
     */
    public function moveDownAction(DataIndexerElement $element, $id, Request $request): Response
    {
        $entity = $this->getEntity($element, $id);

        $this->getRepository($element)->moveDown($entity);
        $element->getObjectManager()->flush();

        $this->dispatchEvent(new MovedDownTreeEvent($element, $request, $entity));

        return $this->getRedirectResponse($element, $request);
    }

    private function dispatchEvent(AdminEvent $event): void
    {
        if (true === interface_exists(PsrEventDispatcherInterface::class)
            && true === $this->eventDispatcher instanceof PsrEventDispatcherInterface
        ) {
            $this->eventDispatcher->dispatch($event);
        } else {
            $this->eventDispatcher->dispatch(get_class($event), $event);
        }
    }

    /**
     * @param DataIndexerElement $element
     * @param mixed $id
     * @throws NotFoundHttpException
     * @return object
     */
    private function getEntity(DataIndexerElement $element, $id)
    {
        $entity = $element->getDataIndexer()->getData($id);
        if (null === $entity) {
            throw new NotFoundHttpException(sprintf(
                'Entity for element "%s" with id "%s" was not found!',
                $element->getId(),
                $id
            ));
        }

        return $entity;
    }

    private function getRepository(AdminDoctrineElement $element): NestedTreeRepository
    {
        $repository = $element->getRepository();
        if (false === $repository instanceof NestedTreeRepository) {
            throw new InvalidArgumentException(sprintf(
                'Repository "%s" needs to extend "%s',
                get_class($repository),
                NestedTreeRepository::class
            ));
        }

        return $repository;
    }

    private function getRedirectResponse(AdminElement $element, Request $request): RedirectResponse
    {
        $redirectUri = $request->query->get('redirect_uri');
        if (null !== $redirectUri && '' !== $redirectUri) {
            $uri = $redirectUri;
        } else {
            $uri = $this->router->generate($element->getRoute(), $element->getRouteParameters());
        }

        return new RedirectResponse($uri);
    }
}
