<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Files\BackgroundJob;

use OC\Files\Utils\Scanner;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/**
 * Class ScanFiles is a background job used to run the file scanner over the user
 * accounts to ensure integrity of the file cache.
 *
 * @package OCA\Files\BackgroundJob
 */
class ScanFiles extends TimedJob {
	private IConfig $config;
	private IEventDispatcher $dispatcher;
	private LoggerInterface $logger;
	private IDBConnection $connection;

	/** Amount of users that should get scanned per execution */
	public const USERS_PER_SESSION = 500;

	public function __construct(
		IConfig $config,
		IEventDispatcher $dispatcher,
		LoggerInterface $logger,
		IDBConnection $connection,
		ITimeFactory $time
	) {
		parent::__construct($time);
		// Run once per 10 minutes
		$this->setInterval(60 * 10);

		$this->config = $config;
		$this->dispatcher = $dispatcher;
		$this->logger = $logger;
		$this->connection = $connection;
	}

	protected function runScanner(string $user): void {
		try {
			$scanner = new Scanner(
				$user,
				null,
				$this->dispatcher,
				$this->logger
			);
			$scanner->backgroundScan('');
		} catch (\Exception $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e, 'app' => 'files']);
		}
		\OC_Util::tearDownFS();
	}

	/**
	 * Find a storage which have unindexed files and return a user with access to the storage
	 *
	 * @return string|false
	 */
	private function getUserToScan() {
		$query = $this->connection->getQueryBuilder();
		$query->select('user_id')
			->from('filecache', 'f')
			->innerJoin('f', 'mounts', 'm', $query->expr()->eq('storage_id', 'storage'))
			->where($query->expr()->lt('size', $query->createNamedParameter(0, IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->gt('parent', $query->createNamedParameter(-1, IQueryBuilder::PARAM_INT)))
			->setMaxResults(1);

		return $query->executeQuery()->fetchOne();
	}

	/**
	 * @param $argument
	 * @throws \Exception
	 */
	protected function run($argument) {
		if ($this->config->getSystemValueBool('files_no_background_scan', false)) {
			return;
		}

		$usersScanned = 0;
		$lastUser = '';
		$user = $this->getUserToScan();
		while ($user && $usersScanned < self::USERS_PER_SESSION && $lastUser !== $user) {
			$this->runScanner($user);
			$lastUser = $user;
			$user = $this->getUserToScan();
			$usersScanned += 1;
		}

		if ($lastUser === $user) {
			$this->logger->warning("User $user still has unscanned files after running background scan, background scan might be stopped prematurely");
		}
	}
}
