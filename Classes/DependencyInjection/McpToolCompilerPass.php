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

namespace KonradMichalik\Typo3RoutingMcp\DependencyInjection;

use KonradMichalik\Typo3Routing\Authentication\{BackendUserAuthenticator, FrontendUserAuthenticator};
use KonradMichalik\Typo3Routing\Routing\RouteRegistry;
use KonradMichalik\Typo3RoutingMcp\Attribute\McpTool;
use KonradMichalik\Typo3RoutingMcp\Mcp\ExposurePolicy;
use Override;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use function array_filter;
use function array_map;
use function class_exists;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function sprintf;
use function trigger_error;

use const E_USER_WARNING;

/**
 * McpToolCompilerPass.
 *
 * Runs after the core's RouteCompilerPass (registered at a lower priority — see
 * Configuration/Services.php) and reads back RouteRegistry's already-resolved
 * $routes/$authenticators/$requestTokenScopes constructor arguments, rather than
 * re-deriving route names/prefixing itself. See docs/superpowers/specs/
 * 2026-07-28-mcptool-attribute-exposure-policy-design.md §2 for why.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class McpToolCompilerPass implements CompilerPassInterface
{
    /**
     * @var list<class-string>
     */
    private const SESSION_SCOPED_AUTHENTICATORS = [
        FrontendUserAuthenticator::class,
        BackendUserAuthenticator::class,
    ];

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(RouteRegistry::class) || !$container->hasDefinition(ExposurePolicy::class)) {
            return;
        }

        $registryDefinition = $container->getDefinition(RouteRegistry::class);
        /** @var array<string, array{path: string, methods: list<string>, controller: string, env: string|null, requirements: array<string, string>, priority?: int, defaults?: array<string, mixed>, schemes?: list<string>, host?: string|null, description?: string|null}> $routes */
        $routes = $registryDefinition->getArgument('$routes');
        /** @var array<string, list<array{service: string, options: array<string, mixed>}>> $authenticators */
        $authenticators = $registryDefinition->getArgument('$authenticators');
        /** @var array<string, string> $requestTokenScopes */
        $requestTokenScopes = $registryDefinition->getArgument('$requestTokenScopes');

        $mcpTools = [];
        foreach ($routes as $name => $route) {
            $mcpTool = $this->resolveMcpTool($container, $route['controller']);
            if (null === $mcpTool) {
                continue;
            }

            $mcpTools[$name] = [
                'name' => $mcpTool->name ?? $name,
                'description' => $mcpTool->description ?? $route['description'] ?? null,
                'readOnly' => $mcpTool->readOnly,
                'excludedReason' => $this->exclusionReason($name, $authenticators, $requestTokenScopes),
            ];
        }

        $container->getDefinition(ExposurePolicy::class)->setArgument('$mcpTools', $mcpTools);
    }

    private function resolveMcpTool(ContainerBuilder $container, string $controller): ?McpTool
    {
        [$serviceId, $methodName] = explode('::', $controller, 2);
        if (!$container->hasDefinition($serviceId)) {
            return null;
        }

        $class = $container->getDefinition($serviceId)->getClass();
        if (!is_string($class) || '' === $class) {
            return null;
        }

        $resolvedClass = $container->getParameterBag()->resolveValue($class);
        if (!is_string($resolvedClass) || !class_exists($resolvedClass)) {
            return null;
        }

        $attributes = (new ReflectionMethod($resolvedClass, $methodName))->getAttributes(McpTool::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    /**
     * @param array<string, list<array{service: string, options: array<string, mixed>}>> $authenticators
     * @param array<string, string>                                                      $requestTokenScopes
     */
    private function exclusionReason(string $routeName, array $authenticators, array $requestTokenScopes): ?string
    {
        $reasons = [];

        $sessionAuthenticators = array_filter(
            array_map(static fn (array $auth): string => $auth['service'], $authenticators[$routeName] ?? []),
            static fn (string $class): bool => in_array($class, self::SESSION_SCOPED_AUTHENTICATORS, true),
        );
        if ([] !== $sessionAuthenticators) {
            $reasons[] = sprintf('Guarded by an authenticator requiring a user session, which an MCP client cannot provide (%s).', implode(', ', $sessionAuthenticators));
        }

        if (isset($requestTokenScopes[$routeName])) {
            $reasons[] = 'Requires a request token (CSRF), which is a form-flow artifact an MCP client cannot produce.';
        }

        if ([] === $reasons) {
            return null;
        }

        $reason = implode(' ', $reasons);
        trigger_error(sprintf('Route "%s" carries #[McpTool] but will never be exposed: %s', $routeName, $reason), E_USER_WARNING);

        return $reason;
    }
}
