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


namespace Tollwerk\TwConsole\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tollwerk\TwConsole\Domain\Repository\GeneralRecordsRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Update slugs for a given table and slug field
 */
class SlugService
{
    /**
     * Constructor
     *
     * @param SymfonyStyle|null $io SymfonyStyle
     */
    public function __construct(private readonly ?SymfonyStyle $io = null)
    {
    }

    /**
     * Get records for given table
     *
     * @param string $tableName Table name
     * @param array  $uids      If set, only records with these UIDs will be fetched
     * @param array  $pids      If set, only records with these PIDs will be fetched
     *
     * @return array
     *
     * @throws Exception
     */
    public function getRecords(string $tableName, array $uids = [], array $pids = []): array
    {
        // Create basic query.
        $queryBuilder = GeneralUtility::makeInstance(
            ConnectionPool::class
        )->getQueryBuilderForTable($tableName);
        $queryBuilder
            ->select('*')
            ->from($tableName)
            ->setRestrictions($queryBuilder->getRestrictions()->removeByType(HiddenRestriction::class));

        // Limit query to given UIDs.
        if (!empty($uids)) {
            $queryBuilder
                ->where(
                    $queryBuilder->expr()->in(
                        'uid',
                        $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)
                    )
                );
        }

        // Limit query to given PIDs.
        if (!empty($pids)) {
            $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->in(
                        'pid',
                        $queryBuilder->createNamedParameter($pids, ArrayParameterType::INTEGER)
                    )
                );
        }

        // Execute query, get records.
        $records = $queryBuilder
            ->executeQuery()
            ->fetchAllAssociative();

        return $records;
    }

    /**
     * Create slugs for all given records
     *
     * @param string $table       Table
     * @param string $slugField   Slug field name
     * @param array  $records     Records
     * @param bool   $forceUpdate Force updates for non-empty slugs
     *
     * @return array
     */
    public function createSlugs(string $table, string $slugField, array $records = [], bool $forceUpdate = false): array
    {
        // Do nothing if no records given.
        if (empty($records)) {
            return [];
        }

        // Create SlugHelper.
        $slugHelper = GeneralUtility::makeInstance(
            SlugHelper::class,
            $table,
            $slugField,
            $GLOBALS['TCA'][$table]['columns'][$slugField]['config']
        );

        // Create slugs.
        $slugs = [];
        foreach ($records as $record) {
            // Create slug.
            $performUpdate = $forceUpdate ?: empty($record[$slugField]);
            $slug = [
                'uid' => $record['uid'],
                'update' => $performUpdate,
                'slug' => (
                $performUpdate
                    ? $slugHelper->generate($record, (int)$record['pid'] ?: 1)
                    : $record[$slugField]
                ),
            ];
            $slugs[$slug['uid']] = $slug;
        }

        return $slugs;
    }

    /**
     * Create batches
     *
     * @param array $slugs     Slugs
     * @param int   $batchSize Max size of a single batch
     *
     * @return array
     *
     * TODO: Refactor to generic createBatches() function inside Utility Class, only add the database statement here.
     */
    public function createBatches(array $slugs, int $batchSize = 1000): array
    {
        $batches = [];
        $currentBatch = [];
        foreach ($slugs as $slug) {
            // When current batch is full, save it and start a new batch.
            if (count($currentBatch) >= $batchSize) {
                $batches[] = $currentBatch;
                $currentBatch = [];
            }

            // Store the uid and, if update is necessary, the database statement for the current slug.
            // @extensionScannerIgnoreLine
            $currentBatch[$slug['uid']] = $slug['update']
                ? 'WHEN ' . $slug['uid'] . ' THEN \'' . $slug['slug'] . '\''
                : null;
        }
        $batches[] = $currentBatch;

        return $batches;
    }

    /**
     * Update records, setting the slugs
     *
     * @param string $table     Table name
     * @param string $slugField Slug field name
     * @param array  $slugs     Slugs to set. Array of arrays, each with keys 'uid', 'update' and 'slug'.
     *                          Example: $slugs = [['uid' => 1, 'updated' => true, 'slug' =>
     *                          '/foo/bar/baz']];
     * @param bool   $isPreview If true, no database update will be performed
     *
     * @return int Number of updated records
     */
    public function updateRecords(string $table, string $slugField, array $slugs = [], bool $isPreview = false): int
    {
        // Create batches of slugs.
        $batches = $this->createBatches($slugs);

        // Update slugs by batch.
        $updateCount = 0;
        foreach ($batches as $batch) {
            // Filter batch, removing all slugs that don't need an update.
            $filteredBatch = array_filter($batch);

            // Perform database update if filtered batch has items.
            if (count($filteredBatch)) {
                $updateCount += count($filteredBatch);

                if (!$isPreview) {
                    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                    $queryBuilder
                        ->update($table)
                        ->set(
                            $slugField,
                            sprintf(
                                'CASE `uid` %s ELSE `slug` END',
                                implode(' ', $filteredBatch)
                            ),
                            false
                        )
                        ->executeStatement();
                }
            }

            // Show console output.
            if ($this->io) {
                $verbose = $this->io->isVerbose();
                if ($verbose || $isPreview) {
                    foreach (array_keys($batch) as $uid) {
                        $this->io->text(
                            sprintf(
                                '[%s][%s] %s',
                                $slugs[$uid]['uid'],
                                // @extensionScannerIgnoreLine
                                $slugs[$uid]['update'] ? 'updated' : 'unchanged',
                                $slugs[$uid]['slug']
                            )
                        );
                    }
                }
            }
        }

        return $updateCount;
    }

    /**
     * Update slugs
     *
     * @param string $tableName   Table name
     * @param string $fieldName   Slug field name
     * @param array  $uids        If set, only records with these UIDs will be fetched
     * @param array  $pids        If set, only records with these PIDs will be fetched
     * @param bool   $forceUpdate Force update of already existing slugs
     * @param bool   $preview     Only preview changes, but don't update the database
     *
     * @return int                The number of updated records
     *
     * @throws Exception
     */
    public function update(
        string $tableName = 'pages',
        string $fieldName = 'slug',
        array $uids = [],
        array $pids = [],
        bool $forceUpdate = false,
        bool $preview = false
    ): int {
        // Get all PIDs recursively.
        if (!empty($pids)) {
            $generalRecordsRepository = GeneralUtility::makeInstance(GeneralRecordsRepository::class);
            $recursivePids = [];
            foreach ($pids as $pid) {
                $recursivePids = array_merge(
                    $recursivePids,
                    $generalRecordsRepository->getAllSubpageIdentifiers($pid, $tableName)
                );
            }
            $pids = $recursivePids;
        }

        // Get records and update their slugs.
        $records = $this->getRecords($tableName, $uids, $pids);
        $slugs = $this->createSlugs($tableName, $fieldName, $records, $forceUpdate);
        return $this->updateRecords($tableName, $fieldName, $slugs, $preview);
    }
}
