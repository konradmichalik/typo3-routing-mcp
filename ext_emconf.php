<?php

/*
 * This file is part of the "routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'Routing MCP',
    'description' => 'Expose annotated typo3-routing frontend routes as MCP tools via a Streamable HTTP endpoint.',
    'category' => 'fe',
    'author' => 'Konrad Michalik',
    'author_email' => 'hej@konradmichalik.dev',
    'state' => 'stable',
    'version' => '0.1.0',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.5.99',
            'typo3' => '13.4.0-14.99.99',
            'typo3_routing' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
