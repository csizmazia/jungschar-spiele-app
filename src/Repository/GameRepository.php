<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

class GameRepository
{
    protected const BASE_QUERY = 'SELECT %s FROM modelle WHERE kategorie = "Spiele" AND publikation = "Spielemappe"';
    protected const DEFAULT_ORDER = ' ORDER BY thema';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $dbConnection;

    /**
     * GameRepository constructor.
     *
     * @param \Doctrine\DBAL\Connection $dbConnection
     */
    public function __construct(Connection $dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    /**
     * @return array|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findRandom(): ?array
    {
        $stmt = $this->dbConnection->query(
            sprintf(static::BASE_QUERY, 'modelle.*, RAND() AS random') . ' ORDER BY random LIMIT 1'
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * @param string $name
     *
     * @return array|null
     * @throws \Doctrine\DBAL\DBALException
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->dbConnection->query(
            sprintf(
                static::BASE_QUERY . ' AND thema = "%s"' . static::DEFAULT_ORDER,
                '*',
                $name
            )
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * @param string $query
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function autocompleteSearch(string $query): array
    {
        $stmt = $this->dbConnection->query(
            sprintf(
                static::BASE_QUERY . ' AND thema LIKE "%%%s%%"' . static::DEFAULT_ORDER,
                'thema',
                $query
            )
        );

        return $stmt->fetchAll();
    }

    /**
     * @param array $filters
     *
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function search(array $filters = []): array
    {
        $params = ['*'];
        $and = [];

        if (isset($filters['keyword']) && $filters['keyword']) {
            $and[] = '(MATCH(art,thema,langtext,variante) AGAINST ("%s") OR art LIKE "%%%s%%" OR thema LIKE "%%%s%%" OR langtext LIKE "%%%s%%" OR variante LIKE "%%%s%%")';
            $params[] = $filters['keyword'];
            $params = array_merge($params, array_fill(0, 4, $filters['keyword']));
        }
        if (isset($filters['age_min']) && $filters['age_min']) {
            $and[] = 'alter_unten >= %d';
            $params[] = (int)$filters['age_min'];
        }
        if (isset($filters['age_max']) && $filters['age_max']) {
            $and[] = 'alter_oben <= %d';
            $params[] = (int)$filters['age_max'];
        }
        if (isset($filters['groupsize_min']) && $filters['groupsize_min']) {
            $and[] = 'anzahl_unten >= %d';
            $params[] = (int)$filters['groupsize_min'];
        }
        if (isset($filters['groupsize_max']) && $filters['groupsize_max']) {
            $and[] = 'anzahl_oben >= %d';
            $params[] = (int)$filters['groupsize_max'];
        }
        if (isset($filters['type'])) {
            $and[] = 'art LIKE "%%%s%%"';
            $params[] = $filters['type'];
        }

        $stmt = $this->dbConnection->query(
            sprintf(
                static::BASE_QUERY . (count($and) ? (' AND ' . implode(' AND ', $and)) : '') . static::DEFAULT_ORDER,
                ...$params
            )
        );

        return $stmt->fetchAll();
    }

    /**
     * @return int
     * @throws \Doctrine\DBAL\DBALException
     */
    public function countAll(): int
    {
        $stmt = $this->dbConnection->query(
            sprintf(static::BASE_QUERY, 'COUNT(*) AS cnt')
        );

        return (int)$stmt->fetch()['cnt'];
    }
}
