<?php


namespace App\Controller;


use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Responses\EmptyResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class ApiTokenController
{
    /**
     * @var Security
     */
    private $security;

    /**
     * @var ApiTokenRepository
     */
    private $apiTokenRepository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * ApiTokenController constructor.
     * @param Security $security
     * @param ApiTokenRepository $apiTokenRepository
     * @param SerializerInterface $serializer
     */
    public function __construct(Security $security, ApiTokenRepository $apiTokenRepository, SerializerInterface $serializer)
    {
        $this->security = $security;
        $this->apiTokenRepository = $apiTokenRepository;
        $this->serializer = $serializer;
    }

    /**
     * @Route("/api/tokens", methods={"POST"})
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function createToken()
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $token = new ApiToken($currentUser);
        $token = $this->apiTokenRepository->save($token);
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
        $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if ($currentUser->getId() !== $token->getUser()->getId() && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('delete this token');
        }
        $this->apiTokenRepository->delete($token);
        return new EmptyResponse();
    }

    /**
     * @Route("/api/tokens", methods={"GET"})
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getTokens()
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->hasRole('ROLE_ADMIN')) {
            $tokens = $this->apiTokenRepository->findAll();
        } else {
            $tokens = $this->apiTokenRepository->findBy(['user_id', $currentUser->getId()]);
        }
        return new JsonResponse($this->serializeTokens($tokens));
    }

    /**
     * @Route("/api/token/{id}", methods={"GET"})
     * @param string $id
     * @return JsonResponse|NoRightsResponse
     */
    public function getToken(string $id)
    {
        $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$token->getUser()->getId() === $currentUser->getId()) {
            return new NoRightsResponse('see this users token');
        }
        return new JsonResponse($this->serializeTokens($token));
    }

    /**
     * @Route("/api/token/{id}/secret", methods={"GET"})
     * @param string $id
     * @return JsonResponse|NoRightsResponse
     */
    public function getTokenSecret(string $id)
    {
        $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$token->getUser()->getId() === $currentUser->getId()) {
            return new NoRightsResponse('see this users token');
        }
        return new JsonResponse(['token' => $token->getToken()], 200, true);
    }

    /**
     * @param ApiToken|ApiToken[] $tokens
     * @return string
     */
    private
    function serializeTokens($tokens)
    {
        $callback = function ($attributeValue, $object) {
            return '/user/' . $object->getUser()->getUsername();
        };
        $context = [
            AbstractNormalizer::CALLBACKS =>
                [
                    'user' => $callback
                ],
            AbstractNormalizer::IGNORED_ATTRIBUTES => ['token']
        ];
        return $this->serializer->serialize($tokens, 'json', $context);
    }
}