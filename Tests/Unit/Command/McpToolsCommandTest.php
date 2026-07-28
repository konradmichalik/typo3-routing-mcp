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

namespace KonradMichalik\Typo3RoutingMcp\Tests\Unit\Command;

use KonradMichalik\Ttt\Attribute\WithEnvironment;
use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use KonradMichalik\Typo3RoutingMcp\Command\McpToolsCommand;
use KonradMichalik\Typo3RoutingMcp\Mcp\ExposurePolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * McpToolsCommandTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class McpToolsCommandTest extends TestCase
{
    #[Test]
    #[WithEnvironment(context: 'Production')]
    public function rendersToolsAsTable(): void
    {
        $tester = $this->tester();

        $exitCode = $tester->execute([]);
        $display = $tester->getDisplay();

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('course_show', $display);
        self::assertStringContainsString('Exposed', $display);
        self::assertStringContainsString('secure_route', $display);
        self::assertStringContainsString('Excluded: Guarded', $display);
        self::assertStringContainsString('dev_only_route', $display);
        self::assertStringContainsString('Excluded: environment mismatch', $display);
    }

    #[Test]
    #[WithEnvironment(context: 'Production')]
    public function rendersToolsAsJson(): void
    {
        $tester = $this->tester();

        $tester->execute(['--json' => true]);

        /** @var list<array{route: string, name: string, description: string|null, readOnly: bool, status: string}> $data */
        $data = json_decode(trim($tester->getDisplay()), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('course_show', $data[0]['route']);
        self::assertSame('Exposed', $data[0]['status']);
    }

    #[Test]
    public function warnsWhenNoRoutesCarryMcpTool(): void
    {
        $registry = new RouteRegistry([], new ServiceLocator([]));
        $exposurePolicy = new ExposurePolicy($registry, []);
        $tester = new CommandTester(new McpToolsCommand($exposurePolicy));

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('No routes carry #[McpTool]', $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $routes = [
            'course_show' => ['path' => '/api/courses/{id}', 'methods' => ['GET'], 'controller' => 'ctrl::show', 'env' => null, 'requirements' => []],
            'secure_route' => ['path' => '/api/secure', 'methods' => ['GET'], 'controller' => 'ctrl::secure', 'env' => null, 'requirements' => []],
            'dev_only_route' => ['path' => '/api/dev', 'methods' => ['GET'], 'controller' => 'ctrl::dev', 'env' => 'Development', 'requirements' => []],
        ];

        $mcpTools = [
            'course_show' => ['name' => 'course_show', 'description' => 'Fetch a course.', 'readOnly' => true, 'excludedReason' => null],
            'secure_route' => ['name' => 'secure_route', 'description' => null, 'readOnly' => false, 'excludedReason' => 'Guarded by an authenticator requiring a user session, which an MCP client cannot provide (Acme\\FrontendUserAuthenticator).'],
            'dev_only_route' => ['name' => 'dev_only_route', 'description' => null, 'readOnly' => false, 'excludedReason' => null],
        ];

        $registry = new RouteRegistry($routes, new ServiceLocator([]));
        $exposurePolicy = new ExposurePolicy($registry, $mcpTools);

        return new CommandTester(new McpToolsCommand($exposurePolicy));
    }
}
