<?php


namespace App\Controller;


use App\Entity\ApiToken;
use App\Entity\User;
use App\Repository\ApiTokenRepository;
use App\Repository\UserRepository;
use App\Responses\EmptyResponse;
use App\Responses\JsonResponse;
use App\Responses\NoRightsResponse;
use App\Responses\NotFoundResponse;
use App\Responses\RedirectResponse;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
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
     * @Route("/api/user/{username}/tokens", methods={"POST"})
     * @param UserRepository $userRepository
     * @param string $username
     * @return NoRightsResponse|NotFoundResponse|RedirectResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createToken(UserRepository $userRepository, string $username)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('add this token');
        }
        $user = $userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        $token = new ApiToken($user);
        $token = $this->apiTokenRepository->save($token);
        return new RedirectResponse('/user/' . $username . '/token/' . $token->getId());
    }

    /**
     * @Route("/api/user/{username}/token/{id}", methods={"DELETE"})
     * @param string $username
     * @param int $id
     * @return EmptyResponse|NoRightsResponse|NotFoundResponse
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteToken(string $username, int $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('delete this token');
        }
        $token = $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if (!$token->getUser()->getId() === $username) {
            return new NoRightsResponse('delete this users token');
        }
        $this->apiTokenRepository->delete($token);
        return new EmptyResponse();
    }

    /**
     * @Route("/api/user/{username}/tokens", methods={"GET"})
     * @param UserRepository $userRepository
     * @param string $username
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getUsersTokens(UserRepository $userRepository, string $username)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        $user = $userRepository->findOneBy(['username' => $username]);
        if ($user === null) {
            return new NotFoundResponse('user');
        }
        if ($currentUser->getId() !== $username && !$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this users tokens');
        }
        $tokens = $this->apiTokenRepository->findBy(['user_id' => $user->getId()]);
        return new JsonResponse($this->serializeTokens($tokens));
    }

    /**
     * @Route("/api/tokens", methods={"GET"})
     * @return JsonResponse|NoRightsResponse|NotFoundResponse
     */
    public function getTokens()
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if (!$currentUser->hasRole('ROLE_ADMIN')) {
            return new NoRightsResponse('see this tokens');
        }
        $tokens = $this->apiTokenRepository->findAll();
        return new JsonResponse($this->serializeTokens($tokens));
    }

    /**
     * @Route("/api/user/{username}/token/{id}", methods={"GET"})
     * @param string $username
     * @param string $id
     * @return JsonResponse|NoRightsResponse
     */
    public function getToken(string $username, string $id)
    {
        /** @var User $currentUser */
        $currentUser = $this->security->getUser();
        if ($currentUser->getId() === $username) {
            return new NoRightsResponse('see this tokens');
        }

        $token = $token = $this->apiTokenRepository->findOneBy(['id' => $id]);
        if ($token === null) {
            return new NotFoundResponse('token');
        }
        if (!$token->getUser()->getId() === $username) {
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