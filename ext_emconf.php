<?php

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

$EM_CONF['tw_console'] = [
    'title' => 'tollwerk Console',
    'description' => 'Collection of useful console commands for TYPO3',
    'category' => 'plugin',
    'author' => 'tollwerk GmbH',
    'author_email' => 'info@tollwerk.de',
    'author_company' => 'tollwerk GmbH',
    'state' => 'alpha',
    'version' => '0.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '12.0.0-12.4.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
