<?php

namespace App\Controller;

use App\Repository\GameRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GameController extends AbstractController
{
    protected const GAME_TYPES = [
        'Bewegung',
        'Erraten',
        'Fangen',
        'Herstellen',
        'Miteinander',
        'Reden',
        'Rollenspiele',
        'Suchen',
    ];

    /**
     * @var \App\Repository\GameRepository
     */
    protected $gameRepository;

    /**
     * PageController constructor.
     *
     * @param \App\Repository\GameRepository $gameRepository
     */
    public function __construct(GameRepository $gameRepository)
    {
        $this->gameRepository = $gameRepository;
    }

    /**
     * @param string $name
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function game(string $name): Response
    {
        try {
            return $this->renderSingle($this->gameRepository->findByName($name));
        } catch (Throwable $e) {
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function random(): Response
    {
        try {
            return $this->renderSingle($this->gameRepository->findRandom());
        } catch (Throwable $e) {
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     */
    public function search(): Response
    {
        try {
            return $this->renderList(
                $this->gameRepository->search(
                    $this->get('request_stack')->getCurrentRequest()->request->all()
                )
            );
        } catch (Throwable $e) {
            return $this->renderError($e->getMessage());
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function autocomplete(): JsonResponse
    {
        return new JsonResponse(
            [
                'options' =>
                    array_reduce(
                        $this->gameRepository->autocompleteSearch(
                            $this->get('request_stack')->getCurrentRequest()->query->getAlnum('query')
                        ),
                        function (array $carry, array $game): array {
                            $carry[] = $game['Thema'];

                            return $carry;
                        },
                        []
                    ),
            ]

        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     */
    public function count(): Response
    {
        return new Response('' . $this->gameRepository->countAll());
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\DBAL\DBALException
     */
    public function export(): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'application/xml');

        return $this->render(
            'list.xml.twig',
            [
                'games' => $this->gameRepository->search(),
                'gameTypes' => static::GAME_TYPES,
            ],
            $response
        );
    }

    /**
     * @param array $games
     *
     * @return array
     */
    protected function groupGames(array $games): array
    {
        $umlaute = ['ä' => 'a', 'Ä' => 'a', 'ö' => 'o', 'Ö' => 'o', 'ü' => 'u', 'Ü' => 'u'];

        $grouped = [];
        foreach ($games as $game) {
            $group = mb_strtolower(mb_substr($game['Thema'], 0, 1));
            $group = $umlaute[$group] ?? $group;

            $grouped[$group][] = $game;
        }

        return $grouped;
    }

    /**
     * @param array $games
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderList(array $games): Response
    {
        $formData = $this->get('request_stack')->getCurrentRequest()->request->all();
        $userDidSearch = count($formData) > 0;

        return $this->render(
            'list.html.twig',
            [
                'games' => $this->groupGames($games),
                'search' => $formData,
                'headerSmaller' => $userDidSearch,
                'collapseSearchBoxDetails' => $userDidSearch,
                'showSearchBoxButtons' => !$userDidSearch,
                'gameTypes' => static::GAME_TYPES,
            ]
        );
    }

    /**
     * @param array|null $game
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderSingle(?array $game): Response
    {
        if ($game) {
            return $this->render(
                'single.html.twig',
                [
                    'game' => $game,
                ]
            );
        }

        return $this->renderList([]);
    }

    /**
     * @param string $message
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderError(string $message): Response
    {
        return $this->render('error.html.twig', ['error' => $message]);
    }
}
