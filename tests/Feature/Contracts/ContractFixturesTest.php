<?php

namespace Tests\Feature\Contracts;

use JsonException;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ContractFixturesTest extends TestCase
{
    /** @var list<string> */
    private const REQUIRED_EVENT_FIELDS = [
        'eventId',
        'matchId',
        'inningsId',
        'sequence',
        'eventType',
        'schemaVersion',
        'ruleProfileVersion',
        'deviceId',
        'scoringSessionId',
        'baseServerVersion',
        'occurredAtUtc',
        'createdAtUtc',
        'payload',
    ];

    /** @throws JsonException */
    public function test_scoring_event_v1_fixture_matches_the_canonical_envelope(): void
    {
        $event = $this->readContractFixture('scoring-event-v1.json');

        foreach (self::REQUIRED_EVENT_FIELDS as $field) {
            $this->assertArrayHasKey($field, $event);
        }

        $this->assertSame(1, $event['schemaVersion']);
        $this->assertGreaterThanOrEqual(1, $event['sequence']);
        $this->assertGreaterThanOrEqual(0, $event['baseServerVersion']);

        foreach (['eventId', 'matchId', 'inningsId', 'deviceId', 'scoringSessionId'] as $field) {
            $this->assertMatchesRegularExpression(
                '/^[0-9A-HJKMNP-TV-Z]{26}$/',
                $event[$field],
                "{$field} must be an uppercase ULID.",
            );
        }

        foreach (['occurredAtUtc', 'createdAtUtc'] as $field) {
            $this->assertStringEndsWith('Z', $event[$field], "{$field} must be UTC.");
        }

        $this->assertIsArray($event['payload']);
    }

    /** @throws JsonException */
    public function test_conflict_fixture_exposes_a_stable_machine_readable_error(): void
    {
        $fixture = $this->readContractFixture('api-error-conflict-v1.json');
        $error = $fixture['error'];
        $conflict = $error['conflict'];

        $this->assertSame('match_version_conflict', $error['code']);
        $this->assertNotSame('', $error['message']);
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $error['requestId']);

        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $conflict['matchId']);
        $this->assertGreaterThan($conflict['clientBaseVersion'], $conflict['serverVersion']);
        $this->assertSame('reload_and_reconcile', $conflict['recoveryAction']);
    }

    public function test_openapi_contract_has_unique_operations_and_resolvable_local_references(): void
    {
        $contract = Yaml::parseFile(base_path('../contracts/openapi.yaml'));
        $operationIds = [];

        foreach ($contract['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertContains($method, ['get', 'post', 'put', 'patch', 'delete']);
                $this->assertArrayHasKey('operationId', $operation, "{$method} {$path} requires an operationId.");
                $operationIds[] = $operation['operationId'];
            }
        }

        $this->assertSame($operationIds, array_values(array_unique($operationIds)));

        foreach ($this->collectLocalReferences($contract) as $reference) {
            $this->assertNotNull(
                $this->resolveLocalReference($contract, $reference),
                "OpenAPI reference {$reference} must resolve.",
            );
        }

        foreach (['/auth/register', '/players', '/teams', '/matches', '/matches/{matchId}/events/batch', '/matches/{matchId}/projection', '/tournaments'] as $path) {
            $this->assertArrayHasKey($path, $contract['paths']);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function readContractFixture(string $name): array
    {
        $contents = file_get_contents(base_path("../contracts/fixtures/{$name}"));

        $this->assertIsString($contents, "Contract fixture {$name} must be readable.");

        return json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return list<string>
     */
    private function collectLocalReferences(array $value): array
    {
        $references = [];

        array_walk_recursive($value, function (mixed $item, string|int $key) use (&$references): void {
            if ($key === '$ref' && is_string($item) && str_starts_with($item, '#/')) {
                $references[] = $item;
            }
        });

        return array_values(array_unique($references));
    }

    /** @param array<string, mixed> $contract */
    private function resolveLocalReference(array $contract, string $reference): mixed
    {
        $current = $contract;

        foreach (explode('/', substr($reference, 2)) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
