<?php

namespace App\Controller;

use App\Entity\Volume;
use App\Repository\VolumeRepository;
use App\Responses\EmptyResponse;
use App\Responses\NotFoundOrNoRightsResponse;
use App\Responses\RedirectResponse;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Class VolumeController
 * @package App\Controller
 */
class VolumeController
{

    /**
     * @var Security
     */
    private $security;

    /**
     * @var VolumeRepository
     */
    private $volumeRepository;


    /**
     * VolumeController constructor.
     * @param Security $security
     * @param VolumeRepository $volumeRepository
     */
    public function __construct(Security $security, VolumeRepository $volumeRepository)
    {
        $this->security = $security;
        $this->volumeRepository = $volumeRepository;
    }

    /**
     * @Route("/volumes", methods={"GET"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @return Response
     */
    public function getVolumes(Request $request, SerializerInterface $serializer)
    {
        if (in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            $volumesCollection = $this->volumeRepository->findAll();
        } else {
            $volumesCollection = $this->security->getUser()->getVolumes();
        }
        $volumes = [];
        if (count($volumesCollection) > 0) {
            foreach ($volumesCollection as $volume) {
                $volumes[] = $volume;
            }
        }
        return new Response($this->serializeVolumes($serializer, $volumes));
    }

    /**
     * @Route("/volumes", methods={"POST"})
     * @param Request $request
     * @return Response
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addVolume(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $volume = new Volume($data['name'], $data['secret']);
        $volume->setUser($this->security->getUser());
        $volume = $this->volumeRepository->save($volume);
        return new RedirectResponse('/volume/' . $volume->getId());
    }

    /**
     * @Route("/volume/{id}", methods={"GET"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param int $id
     * @return Response
     */
    public function getVolume(Request $request, SerializerInterface $serializer, int $id)
    {
        $volume = $this->volumeRepository->findOneBy(['id' => $id]);
        if ($volume !== null && $volume->getUser()->getUsername() === $this->security->getUser()->getUsername()) {
            $json = $this->serializeVolumes($serializer, $volume);
            return new Response($json);
        }
        return new NotFoundOrNoRightsResponse('volume');
    }

    /**
     * @Route("/volume/{id}", methods={"PATCH"})
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param int $id
     * @return NotFoundOrNoRightsResponse|Response
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function changeVolume(Request $request, SerializerInterface $serializer, int $id)
    {
        $volume = $this->volumeRepository->findOneBy(['id' => $id]);
        if ($volume !== null && $volume->getUser()->getUsername() === $this->security->getUser()->getUsername()) {
            $data = json_decode($request->getContent(), true);
            if (isset($data['name'])) {
                $volume->setName($data['name']);
            }
            if (isset($data['secret'])) {
                $volume->setSecret($data['secret']);
            }
            $this->volumeRepository->save($volume);
            return new RedirectResponse('/volume/' . $id);
        }
        return new NotFoundOrNoRightsResponse('volume');
    }

    /**
     * @Route("/volume/{id}", methods={"DELETE"})
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws ORMException
     */
    public function deleteVolume(Request $request, int $id)
    {
        $volume = $this->volumeRepository->findOneBy(['id' => $id]);
        if ($volume->getUser()->getUsername() === $this->security->getUser()->getUsername()) {
            $this->volumeRepository->delete($volume);
            return new EmptyResponse();
        }
        return new NotFoundOrNoRightsResponse('volume');
    }

    /**
     * @Route("/volume/{id}/secret", methods={"GET"})
     * @param Request $request
     * @param $id
     * @return Response
     * @throws ORMException
     */
    public function getSecret(Request $request, int $id)
    {
        $volume = $this->volumeRepository->findOneBy(['id' => $id]);
        if (
            ($volume !== null && $volume->getUser()->getUsername() === $this->security->getUser()->getUsername())
            || in_array('ROLE_ADMIN', $this->security->getUser()->getRoles())) {
            $this->volumeRepository->delete($volume);
            return new Response($volume->getSecret(), Response::HTTP_OK, ['content-type' => 'text/plain']);
        }
        return new NotFoundOrNoRightsResponse('volume');
    }

    /**
     * @param SerializerInterface $serializer
     * @param Volume|Volume[] $volumes
     * @return string
     */
    private function serializeVolumes(SerializerInterface $serializer, $volumes)
    {
        $userCallback = function ($attributeValue, $object, $attribute, $format, $context) {
            return '/user/' . $object->getUser()->getId();
        };
        $context = [
            AbstractNormalizer::CALLBACKS => ['user' => $userCallback],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['secret']
        ];
        return $serializer->serialize($volumes, 'json', $context);
    }
}
