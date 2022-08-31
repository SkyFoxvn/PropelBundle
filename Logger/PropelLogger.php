<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\VarDumper\Caster\TraceStub;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
class PropelLogger implements LoggerInterface
{
    protected ?LoggerInterface $logger = null;
    /** @var array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: TraceStub}>  */
    protected array $queries = array();
    protected ?Stopwatch $stopwatch;
    private bool $isPrepared;

    use LoggerTrait;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger    A LoggerInterface instance
     * @param Stopwatch       $stopwatch A Stopwatch instance
     */
    public function __construct(?LoggerInterface $logger = null, ?Stopwatch $stopwatch = null)
    {
        $this->logger    = $logger;
        $this->stopwatch = $stopwatch;
        $this->isPrepared = false;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param  mixed  $level
     * @param  string $message
     * @param  array<mixed>  $context
     */
    public function log($level, $message, array $context = array()): void
    {
        if (null === $this->logger) {
            return;
        }

        $add = true;
        $trace = debug_backtrace();

        if (null !== $this->stopwatch) {
            $method = $trace[3]['function'];

            $watch = 'Propel Query '.(count($this->queries)+1);
            if ('prepare' === $method) {
                $this->isPrepared = true;
                $this->stopwatch->start($watch, 'propel');

                $add = false;
            } elseif ($this->isPrepared) {
                $this->isPrepared = false;
                $event = $this->stopwatch->stop($watch);
            }
        }

        // $trace[2] has no 'object' key if an exception is thrown while executing a query
        if ($add && isset($event) && isset($trace[2]['object'])) {
            $connection = $trace[2]['object'];

            $this->queries[] = array(
                'sql'           => $message,
                'connection'    => $connection->getName(),
                'time'          => $event->getDuration() / 1000,
                'memory'        => $event->getMemory(),
                'trace'         => new TraceStub($trace),
            );
        }

        $this->logger->log($level, $message, $context);
    }

    /**
     * @return array<int, array{sql: string, connection: string, time: int|float, memory: int, trace: TraceStub}>
     */
    public function getQueries(): array
    {
        return $this->queries;
    }
}
