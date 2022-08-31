<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\DataCollector;

use Propel\Bundle\PropelBundle\Logger\PropelLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\VarDumper\Caster\TraceStub;

/**
 * The PropelDataCollector collector class collects information.
 *
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class PropelDataCollector extends DataCollector
{
    protected PropelLogger $logger;

    public function __construct(PropelLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data = array(
            'queries'       => $this->cloneVar($this->buildQueries()),
            'querycount'    => $this->countQueries(),
        );
    }

    /**
     * Returns the collector name.
     *
     * @return string The collector name.
     */
    public function getName(): string
    {
        return 'propel';
    }

    /**
     * Returns queries.
     *
     * @return array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: TraceStub}> Queries
     */
    public function getQueries(): array
    {
        return $this->data['queries'];
    }

    /**
     * Returns the query count.
     *
     * @return int The query count
     */
    public function getQueryCount()
    {
        return $this->data['querycount'];
    }

    /**
     * Returns the total time of queries.
     *
     * @return float The total time of queries
     */
    public function getTime()
    {
        $time = 0;
        foreach ($this->data['queries'] as $query) {
            $time += (float) $query['time'];
        }

        return $time;
    }

    /**
     * Creates an array of Build objects.
     *
     * @return array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: TraceStub}> An array of Build objects
     */
    private function buildQueries(): array
    {
        return $this->logger->getQueries();
    }

    /**
     * Count queries.
     *
     * @return int The number of queries.
     */
    private function countQueries(): int
    {
        return count($this->logger->getQueries());
    }

    /**
     * @inheritdoc
     */
    public function reset(): void
    {
        // TODO: Implement reset() method.
    }
}
