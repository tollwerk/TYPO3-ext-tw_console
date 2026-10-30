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

namespace Tollwerk\TwConsole\Command;

use Doctrine\DBAL\Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tollwerk\TwConsole\Service\SlugService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Update slugs for a given table and slug field
 */
#[AsCommand(
    name: 'twconsole:slugs:update',
    description: 'Update slugs of a given table. Without any arguments or options, empty `pages.slug` fields will be updated.',
)]
class SlugUpdateCommand extends Command
{
    /**
     * SymfonyStyle
     *
     * @var SymfonyStyle
     */
    protected SymfonyStyle $io;

    /**
     * Configure
     *
     * @return void
     */
    protected function configure(): void
    {
        $this
            ->addArgument(
                'tableName',
                InputArgument::OPTIONAL,
                'The table name',
                'pages'
            )
            ->addArgument(
                'fieldName',
                InputArgument::OPTIONAL,
                'The slug field name',
                'slug'
            )
            ->addOption(
                'uid',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Only update records with the given UIDs',
            )
            ->addOption(
                'pid',
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Only update records with the given PIDs and all children PIDs recursively',
            )
            ->addOption(
                'dry-run',
                'd',
                InputOption::VALUE_NONE,
                'Do dry run only, show preview of all changes but do not update the actual data'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'If set, non empty slugs will be updated as well'
            );
    }

    /**
     * Initialize
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    /**
     * Execute
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     *
     * @return int Exit code
     *
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get arguments and options.
        $table = $input->getArgument('tableName');
        $slugField = $input->getArgument('fieldName');
        $forceUpdate = (bool)$input->getOption('force');
        $pids = array_map('intval', $input->getOption('pid'));
        $uids = array_map('intval', $input->getOption('uid'));
        $isDryRun = (bool)$input->getOption('dry-run');

        // Show console output.
        if ($this->io->isVerbose() || $isDryRun) {
            $this->io->title(
                sprintf(
                    'Update slugs for `%s.%s`%s',
                    $table,
                    $slugField,
                    $isDryRun ? ' (preview mode)' : '',
                )
            );
        }

        // Get SlugService and perform update.
        $slugService = GeneralUtility::makeInstance(SlugService::class, $this->io);
        $updateCount = $slugService->update($table, $slugField, $uids, $pids,  $forceUpdate, $isDryRun);

        // Show console output.
        if ($this->io->isVerbose() || $isDryRun) {
            $this->io->success(
                sprintf(
                    'Updated %s records%s',
                    $updateCount,
                    $isDryRun ? ' (not really, because of preview mode)' : ''
                )
            );
        }

        return Command::SUCCESS;
    }
}
