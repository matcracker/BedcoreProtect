<?php

namespace matcracker\BedcoreProtect\storage;

use Generator;
use InvalidArgumentException;
use poggit\libasynql\DataConnector;
use poggit\libasynql\generic\GenericStatementImpl;
use poggit\libasynql\generic\GenericVariable;
use poggit\libasynql\libs\SOFe\AwaitGenerator\Await;
use poggit\libasynql\result\SqlInsertResult;
use poggit\libasynql\result\SqlSelectResult;
use poggit\libasynql\SqlDialect;
use poggit\libasynql\SqlThread;
use function array_fill;
use function count;

final class DataConnectorHelper
{
    private function __construct()
    {
        //NOOP
    }

    public static function asyncSelectRaw(DataConnector $connector, string $query, array $args = []): Generator
    {
        //Return $rows
        return yield from Await::promise(function ($resolve, $reject) use ($connector, $query, $args) {
            /** @var SqlSelectResult[] $results */
            $connector->executeImplRaw([$query], [$args], [SqlThread::MODE_SELECT], static function (array $results) use ($resolve): void {
                $result = $results[count($results) - 1];
                $resolve($result->getRows(), $result->getColumnInfo());
            }, $reject);
        });
    }

    public static function asyncInsertRaw(DataConnector $connector, string $query, array $args = []): Generator
    {
        $onSuccess = yield Await::RESOLVE;
        $onError = yield Await::REJECT;
        /** @var SqlInsertResult[] $results */
        $connector->executeImplRaw([$query], $args, [SqlThread::MODE_INSERT], static function (array $results) use ($onSuccess): void {
            $result = $results[count($results) - 1];
            $onSuccess($result->getInsertId(), $result->getAffectedRows());
        }, $onError);

        //Return $affectedRows
        return yield Await::ONCE;
    }

    public static function asyncMultiInsertRaw(DataConnector $connector, array $queries, array $args = []): Generator
    {
        //Return $affectedRows
        return yield from Await::promise(function ($resolve, $reject) use ($connector, $queries, $args) {
            $modes = array_fill(0, count($queries), SqlThread::MODE_INSERT);
            /** @var SqlInsertResult[] $results */
            $connector->executeImplRaw($queries, $args, $modes, static fn(array $results) => $resolve($results), $reject);
        });
    }

    /**
     * @param string $dialect
     * @param string $query
     * @param array[] $args
     * @param string $statementName
     * @param GenericVariable[] $variables
     * @param array $parameters
     */
    public static function asGenericStatement(string $dialect, string &$query, array &$args, string $statementName, array $variables, array $parameters): void
    {
        if ($dialect !== SqlDialect::SQLITE && $dialect !== SqlDialect::MYSQL) {
            throw new InvalidArgumentException("Invalid dialect $dialect");
        }

        $placeholder = match ($dialect) {
            SqlDialect::SQLITE => null,
            SqlDialect::MYSQL => "?"
        };

        $statement = GenericStatementImpl::forDialect(
            $dialect,
            $statementName,
            [$query],
            "",
            $variables,
            null,
            0
        );

        [$query] = $statement->format($parameters, $placeholder, $args);
    }
}