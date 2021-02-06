<?php


namespace App\Controller;

use App\Entity\VolumeToken;
use App\Entity\User;
use App\Repository\VolumeRepository;
use App\Repository\VolumeTokenRepository;
use App\Responses\EmptyResponse;
use App\Responses\ErrorResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use App\Util\PasswordGenerator;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class VolumeTokenController
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var VolumeTokenRepository
     */
    private $volumeTokenRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * VolumeTokenController constructor.
     * @param Security $security
     * @param $volumeTokenRepository $volumeTokenRepository
     * @param SerializerInterface $serializer
     */
    public function __construct(Security $security, VolumeTokenRepository $volumeTokenRepository, SerializerInterface $serializer)
    {
        $this->security = $security;
        $this->volumeTokenRepository = $volumeTokenRepository;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/api/tokens", methods={"POST"})
     * @param Request $request
     * @param VolumeRepository $volumeRepository
     * @param PasswordGenerator $passwordGenerator
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse|ErrorResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createToken(Request $request, VolumeRepository $volumeRepository, PasswordGenerator $passwordGenerator)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $data = json_decode($request->getContent(), true);
        if (!isset($data['volumeId'])) {
            return new ErrorResponse('the attribute volumeId must be set', 400);
        }
        $volume = $volumeRepository->findOneBy(['id' => $data['volumeId']]);
        if ($volume === null) {
            return new NotFoundResponse('volume');
        }
        if ($volume->getUser()->getId() === $currentUser->getId()
            && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('access this volume');
        }
        $tokenValue = $passwordGenerator->generate(42);
        $token = new VolumeToken($volume, $tokenValue);
        $token = $this->volumeTokenRepository->save($token);
        return new RedirectResponse('/token/' . $token->getId());
    }

    /**
     * @Route("/api/token/{id}", methods={"DELETE"})
     * @param int $id
     * @return EmptyResponse|NoRightsResponse|NotFoundResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteToken(int $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $token = $this->volumeTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if ($currentUser->getId() !== $token->getVolume()->getUser()->getId()
            && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('delete this token');
        }
        $this->volumeTokenRepository->delete($token);
        return new EmptyResponse();
    }

    /**
     * @Route("/api/tokens/volume/{volumeId}", methods={"GET"})
     * @param int $volumeId
     * @param VolumeRepository $volumeRepository
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getTokens(int $volumeId, VolumeRepository $volumeRepository)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->hasRole('ROLE_ADMIN')) {
            $tokens = $this->volumeTokenRepository->findAll();
        } else {
            $volume = $volumeRepository->findOneBy(['id' => $volumeId]);
            if ($volume === null) {
                return new NotFoundResponse('volume');
            }
            if ($currentUser->getId() !== $volume->getUser()->getId()) {
                return new NoRightsResponse('access this object');
            }
            $tokens = $this->volumeTokenRepository->findBy(['volume_id', $volumeId]);
        }
        return new JsonResponse($this->serializeTokens($tokens));
    }

    /**
     * @Route("/api/token/{tokenId}", methods={"GET"})
     * @param int $tokenId
     * @return JsonResponse|NoRightsResponse
     */
    public function getToken(int $tokenId)
    {
        $token = $this->volumeTokenRepository->findOneBy(['id' => $tokenId]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($token->getVolume()->getUser()->getId() !== $currentUser->getId()
            && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this users token');
        }
        return new JsonResponse($this->serializeTokens($token));
    }

    /**
     * @Route("/api/token/{tokenId}/secret", methods={"GET"})
     * @param string $tokenId
     * @return JsonResponse|NoRightsResponse
     */
    public function getTokenSecret(string $tokenId)
    {
        $token = $this->volumeTokenRepository->findOneBy(['id' => $tokenId]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($token->getVolume()->getUser()->getId() === $currentUser->getId()
            && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this users token');
        }
        return new JsonResponse(['token' => $token->getToken()], 200, true);
    }

    /**
     * @param VolumeToken|VolumeToken[] $tokens
     * @return string
     */
    private function serializeTokens($tokens)
    {
        $volumeCallback = function ($attributeValue, $object) {
            return $object->getVolume()->getId();
        };
        $dateTimeCallback = function ($attributeValue, $object) {
            return ['stringValue' => $attributeValue->format('Y-m-d H:i:s')];
        };
        $context = [
            AbstractNormalizer::CALLBACKS =>
                [
                    'volume' => $volumeCallback,
                    'dateExpired' => $dateTimeCallback,
                    'dateCreated' => $dateTimeCallback
                ],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['token']
        ];
        return $this->serializer->serialize($tokens, 'json', $context);
    }
}