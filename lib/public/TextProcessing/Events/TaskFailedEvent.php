<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCP\TextProcessing\Events;

use OCP\TextProcessing\Task;

/**
 * @since 27.1.0
 * @deprecated 30.0.0
 */
class TaskFailedEvent extends AbstractTextProcessingEvent {
	/**
	 * @param Task $task
	 * @param string $errorMessage
	 * @since 27.1.0
	 */
	public function __construct(
		Task $task,
		private string $errorMessage,
	) {
		parent::__construct($task);
	}

	/**
	 * @return string
	 * @since 27.1.0
	 */
	public function getErrorMessage(): string {
		return $this->errorMessage;
	}
}
