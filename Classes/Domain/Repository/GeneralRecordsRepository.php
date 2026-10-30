<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Tollwerk\TwConsole\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GeneralRecordsRepository
{
    /**
     * Get all children page uids for a given start page
     *
     * @param int  $pageIdentifier Start page identifier
     * @param bool $addStart       Add start page identifier to list
     * @param bool $addHidden      Should records with pages.hidden=1 be added?
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getAllSubpageIdentifiers(int $pageIdentifier, string $table, bool $addHidden = true): array
    {
        $identifiers = [$pageIdentifier];
        foreach ($this->getChildrenPageIdentifiers($pageIdentifier, $table, $addHidden) as $identifier) {
            $identifiers = array_merge($identifiers, $this->getAllSubpageIdentifiers($identifier, $table, $addHidden));
        }
        return $identifiers;
    }

    /**
     * Get all children uids
     *
     * @param int  $pageIdentifier Start page identifier
     * @param bool $addHidden      Should records with pages.hidden=1 be added?
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\Exception
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    protected function getChildrenPageIdentifiers(int $pageIdentifier, string $table, bool $addHidden): array
    {
        $queryBuilder = GeneralUtility::makeInstance(
            ConnectionPool::class
        )->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->select('uid', 'uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageIdentifier, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq('sys_language_uid', 0)
            );
        if ($addHidden === true) {
            $queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class);
        }

        $result = $queryBuilder->executeQuery()->fetchAllKeyValue();
        return array_values($result);
    }
}
