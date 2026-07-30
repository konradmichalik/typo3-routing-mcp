<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use KonradMichalik\Typo3RoutingMcp\Http\McpEndpointMiddleware;

return [
    'frontend' => [
        'konradmichalik/typo3-routing-mcp/endpoint' => [
            'target' => McpEndpointMiddleware::class,
            // Runs after site resolution so Environment/site context exists and the
            // Host header has already been validated against a configured site
            // (see design doc §1c — this is why DnsRebindingProtectionMiddleware is
            // safely omitted from the transport's own middleware list below).
            // Unlike the core's own RouteDispatcher, this middleware does not also
            // need to run after backend-user-authentication / authentication:
            // ExposurePolicy/McpToolCompilerPass already exclude any route requiring
            // session-scoped authenticators at compile time, and RouteInvoker's
            // invocation path never touches the SecurityAspect state those auth
            // middlewares populate.
            'after' => [
                'typo3/cms-frontend/site',
            ],
            // Runs before page resolving so /_mcp never falls through to the page
            // router, which has nothing mapped to it anyway.
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
