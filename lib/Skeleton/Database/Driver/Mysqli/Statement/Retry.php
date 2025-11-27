<?php

declare(strict_types=1);

/**
 * Database Statement retry Class
 *
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Database\Driver\Mysqli\Statement;

class Retry {

	/**
	 * Handle retry of statement
	 *
	 * @param \Skeleton\Database\Driver\Mysqli\Statement $statement
	 * @param array|null $params
	 * @return boolean
	 */
	public static function handle(
		\Skeleton\Database\Driver\Mysqli\Statement $statement,
		?array $params = null
		): bool
	{
		if ($statement->errno === 0) {
			return true;
		}

		if ($statement->errno !== 1213) {
			// Should we make the list of error numbers configurable?
			throw new \Skeleton\Database\Exception\Query($statement->errno . ': ' . $statement->error);
		}

		if (\Skeleton\Database\Config::$transaction_maximum_retry > 0) {
			for ($x = 1; $x < \Skeleton\Database\Config::$transaction_maximum_retry + 1; $x++) {
				// incremental backoff, until max $transaction_maximum_retry
				$delay = (int)((\Skeleton\Database\Config::$transaction_retry_delay / \Skeleton\Database\Config::$transaction_maximum_retry) * $x);
				$delay = min(\Skeleton\Database\Config::$transaction_retry_delay, $delay);

				usleep($delay);

				self::report($statement, $x);

				try {
					return $statement->execute($params);
				} catch (\Skeleton\Database\Exception\Query $e) {
				}
			}
		}

		throw new \Skeleton\Database\Exception\Query($statement->errno . ': ' . $statement->error);
	}

	/**
	 * Report error via the error handler
	 *
	 * This probably needs some refining, it's a bit much maybe
	 *
	 * @access private
	 * @param \Skeleton\Database\Driver\Mysqli\Statement $statement
	 * @param int $attempt
	 */
	private static function report(\Skeleton\Database\Driver\Mysqli\Statement $statement, int $attempt): void {
		if (\Skeleton\Database\Config::$transaction_retry_report && class_exists('\Skeleton\Error\Handler')) {
			$e = new \Skeleton\Database\Exception\Retry('Database retry report for errno ' . $statement->errno . ': ' . $statement->error . ' attempt ' . $attempt);

			$handler = \Skeleton\Error\Handler::get();
			$handler->report_exception($e);
		}
	}
}
