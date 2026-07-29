<?php

declare(strict_types=1);

/*
 * This file is part of the "routing_mcp" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Http;

use KonradMichalik\Ttt\Attribute\WithEnvVar;
use KonradMichalik\Ttt\Http\Requests;
use KonradMichalik\Typo3Routing\Authentication\BearerTokenAuthenticator;
use KonradMichalik\Typo3RoutingMcp\Http\McpAuthGuard;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * McpAuthGuardTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(McpAuthGuard::class)]
final class McpAuthGuardTest extends TestCase
{
    private const ENV_NAME = 'ROUTING_MCP_TEST_BEARER';

    #[Test]
    #[WithEnvVar(self::ENV_NAME, 's3cret-token')]
    public function acceptsAMatchingTokenAgainstItsOwnConfiguredEnvName(): void
    {
        self::assertTrue($this->guard(self::ENV_NAME)->authenticate($this->request('Bearer s3cret-token')));
    }

    #[Test]
    #[WithEnvVar(self::ENV_NAME, 's3cret-token')]
    public function rejectsAWrongToken(): void
    {
        self::assertFalse($this->guard(self::ENV_NAME)->authenticate($this->request('Bearer wrong')));
    }

    #[Test]
    public function rejectsWhenTheConfiguredEnvVarIsNotSet(): void
    {
        self::assertFalse($this->guard(self::ENV_NAME)->authenticate($this->request('Bearer anything')));
    }

    #[Test]
    #[WithEnvVar('ROUTING_MCP_BEARER_TOKEN', 'default-token')]
    public function fallsBackToTheDefaultEnvNameWhenNoExtensionConfigurationValueIsSet(): void
    {
        self::assertTrue($this->guard('')->authenticate($this->request('Bearer default-token')));
    }

    #[Test]
    #[WithEnvVar('ROUTING_MCP_BEARER_TOKEN', 'default-token')]
    public function fallsBackToTheDefaultEnvNameWhenExtensionConfigurationThrows(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willThrowException(new RuntimeException('not configured'));

        $guard = new McpAuthGuard(new BearerTokenAuthenticator($extensionConfiguration), $extensionConfiguration);

        self::assertTrue($guard->authenticate($this->request('Bearer default-token')));
    }

    #[Test]
    public function readsItsOwnExtensionKeyNotTheCoresWhenResolvingTheConfiguredEnvName(): void
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->expects(self::once())
            ->method('get')
            ->with('routing_mcp', 'bearerTokenEnvName')
            ->willReturn('');

        $guard = new McpAuthGuard(new BearerTokenAuthenticator($extensionConfiguration), $extensionConfiguration);
        $guard->authenticate($this->request('Bearer anything'));
    }

    private function guard(string $configuredEnvName): McpAuthGuard
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($configuredEnvName);

        return new McpAuthGuard(new BearerTokenAuthenticator($extensionConfiguration), $extensionConfiguration);
    }

    private function request(string $authorization): ServerRequest
    {
        return Requests::get('https://example.com/_mcp')
            ->withHeader('Authorization', $authorization)
            ->build();
    }
}
