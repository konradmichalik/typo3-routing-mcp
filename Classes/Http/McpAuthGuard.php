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

namespace KonradMichalik\Typo3RoutingMcp\Http;

use KonradMichalik\Typo3Routing\Authentication\BearerTokenAuthenticator;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

use function is_string;

/**
 * McpAuthGuard.
 *
 * Bearer-token gate for the /_mcp endpoint. Delegates the actual comparison to the
 * core's own BearerTokenAuthenticator — this class's only job is resolving THIS
 * extension's own "bearerTokenEnvName" setting (not typo3_routing's) and passing it
 * as an explicit envName override, since BearerTokenAuthenticator's own fallback
 * reads typo3_routing's extension configuration key, not ours.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class McpAuthGuard
{
    private const DEFAULT_ENV_NAME = 'ROUTING_MCP_BEARER_TOKEN';

    public function __construct(
        private BearerTokenAuthenticator $authenticator,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function authenticate(ServerRequestInterface $request): bool
    {
        return $this->authenticator->authenticate($request, ['envName' => $this->envName()]);
    }

    private function envName(): string
    {
        try {
            $configured = $this->extensionConfiguration->get('typo3_routing_mcp', 'bearerTokenEnvName');
            if (is_string($configured) && '' !== $configured) {
                return $configured;
            }
        } catch (Throwable) {
            // Extension not configured yet — fall back to the default name.
        }

        return self::DEFAULT_ENV_NAME;
    }
}
